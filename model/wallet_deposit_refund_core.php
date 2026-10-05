<?php
// Wallet deposit refunds: shared core (identical copies in nivasity/model and cc_dashboard/model,
// keep them in sync). The command center starts refunds; the Paystack webhook and the command
// center's "Check status" both update them through nvWalletRefundApplyProviderStatus().
//
// Flow: start -> amount taken off the wallet (pending debit) -> Paystack Refund API
//   processed        -> refunded (debit posted, student notified)
//   failed           -> failed   (money put back on the wallet, student notified)
//   needs-attention  -> needs_attention (money stays held; finish it in the Paystack dashboard)
//   pending/processing -> processing
// Needs sql/add_wallet_deposit_refunds.sql.

if (!function_exists('nvWalletRefundReady')) {
  function nvWalletRefundReady(mysqli $conn): bool
  {
    static $ready = null;
    if ($ready === null) {
      $res = mysqli_query($conn, "SHOW TABLES LIKE 'wallet_deposit_refunds'");
      $ready = $res && mysqli_num_rows($res) > 0;
    }
    return $ready;
  }
}

if (!function_exists('nvWalletRefundPaystack')) {
  // Calls the Paystack API. Returns ['ok' => bool, 'http' => int, 'body' => array, 'error' => string]
  function nvWalletRefundPaystack(string $method, string $path, ?array $payload, string $secret): array
  {
    if ($secret === '') {
      return ['ok' => false, 'http' => 0, 'body' => [], 'error' => 'Paystack secret key is not configured'];
    }
    $ch = curl_init('https://api.paystack.co' . $path);
    $headers = ['Authorization: Bearer ' . $secret, 'Content-Type: application/json', 'Cache-Control: no-cache'];
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_TIMEOUT => 45,
      CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    if ($payload !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
      return ['ok' => false, 'http' => $http, 'body' => [], 'error' => 'Could not reach Paystack: ' . $curlError];
    }
    $body = json_decode((string) $raw, true);
    if (!is_array($body)) {
      return ['ok' => false, 'http' => $http, 'body' => [], 'error' => 'Unexpected response from Paystack'];
    }
    $ok = $http >= 200 && $http < 300 && !empty($body['status']);
    return ['ok' => $ok, 'http' => $http, 'body' => $body, 'error' => $ok ? '' : (string) ($body['message'] ?? 'Paystack refused the refund')];
  }
}

if (!function_exists('nvWalletRefundNotify')) {
  // In-app notification for the student (shown in the app/portal notification list).
  function nvWalletRefundNotify(mysqli $conn, int $userId, string $title, string $body, array $data = []): void
  {
    $t = mysqli_real_escape_string($conn, $title);
    $b = mysqli_real_escape_string($conn, $body);
    $d = mysqli_real_escape_string($conn, json_encode($data));
    mysqli_query($conn, "INSERT INTO notifications (user_id, title, body, type, data) VALUES ($userId, '$t', '$b', 'wallet', '$d')");
  }
}

if (!function_exists('nvWalletRefundMoney')) {
  function nvWalletRefundMoney(int $amount): string
  {
    return 'N' . number_format($amount);
  }
}

if (!function_exists('nvWalletRefundFind')) {
  // Finds the refund a Paystack refund/webhook payload belongs to.
  function nvWalletRefundFind(mysqli $conn, array $data): ?array
  {
    $refundId = trim((string) ($data['id'] ?? $data['refund_id'] ?? ''));
    if ($refundId !== '') {
      $safe = mysqli_real_escape_string($conn, $refundId);
      $res = mysqli_query($conn, "SELECT * FROM wallet_deposit_refunds WHERE provider_refund_id = '$safe' LIMIT 1");
      if ($res && ($row = mysqli_fetch_assoc($res))) {
        return $row;
      }
    }
    $txRef = trim((string) ($data['transaction_reference'] ?? ($data['transaction']['reference'] ?? '')));
    if ($txRef !== '') {
      $safe = mysqli_real_escape_string($conn, $txRef);
      $res = mysqli_query($conn, "SELECT * FROM wallet_deposit_refunds WHERE provider_transaction_reference = '$safe'
        ORDER BY (status IN ('processing','needs_attention')) DESC, id DESC LIMIT 1");
      if ($res && ($row = mysqli_fetch_assoc($res))) {
        return $row;
      }
    }
    return null;
  }
}

if (!function_exists('nvWalletRefundReturnToWallet')) {
  // Puts a failed refund's amount back on the wallet (inside the caller's transaction).
  function nvWalletRefundReturnToWallet(mysqli $conn, array $refund, string $reason): int
  {
    $walletId = (int) $refund['wallet_id'];
    $amount = (int) $refund['amount'];
    $wallet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM user_wallets WHERE id = $walletId LIMIT 1 FOR UPDATE"));
    if (!$wallet) {
      throw new RuntimeException('Wallet not found');
    }
    $before = (int) $wallet['balance'];
    $after = $before + $amount;
    $ref = mysqli_real_escape_string($conn, $refund['reference'] . '-REV');
    $desc = mysqli_real_escape_string($conn, 'Refund to bank failed: ' . nvWalletRefundMoney($amount) . ' returned to wallet');
    $meta = mysqli_real_escape_string($conn, json_encode(['refund_id' => (int) $refund['id'], 'reason' => $reason]));
    if (!mysqli_query($conn, "INSERT INTO wallet_ledger_entries (wallet_id, entry_type, amount, balance_before, balance_after, status, reference, provider_reference, description, metadata)
        VALUES ($walletId, 'refund', $amount, $before, $after, 'posted', '$ref', NULL, '$desc', '$meta')")) {
      throw new RuntimeException('Could not record the wallet reversal: ' . mysqli_error($conn));
    }
    $ledgerId = (int) mysqli_insert_id($conn);
    mysqli_query($conn, "UPDATE user_wallets SET balance = $after, updated_at = NOW() WHERE id = $walletId");
    if (!empty($refund['debit_ledger_id'])) {
      mysqli_query($conn, "UPDATE wallet_ledger_entries SET status = 'reversed' WHERE id = " . (int) $refund['debit_ledger_id']);
    }
    return $ledgerId;
  }
}

if (!function_exists('nvWalletRefundApplyProviderStatus')) {
  // Applies a Paystack refund status to our refund. Idempotent: finished refunds are not touched.
  // Returns the refund's status after the update.
  function nvWalletRefundApplyProviderStatus(mysqli $conn, int $refundId, string $providerStatus, array $providerData = [], string $source = 'webhook'): string
  {
    $providerStatus = strtolower(trim($providerStatus));
    mysqli_begin_transaction($conn);
    try {
      $refund = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM wallet_deposit_refunds WHERE id = $refundId LIMIT 1 FOR UPDATE"));
      if (!$refund) {
        throw new RuntimeException('Refund not found');
      }
      $current = (string) $refund['status'];
      if (in_array($current, ['refunded', 'failed'], true)) {
        mysqli_commit($conn);
        return $current; // already final
      }

      $set = ["provider_status = '" . mysqli_real_escape_string($conn, $providerStatus) . "'"];
      if (!empty($providerData)) {
        $set[] = "provider_response = '" . mysqli_real_escape_string($conn, json_encode(['source' => $source, 'data' => $providerData])) . "'";
      }
      if (empty($refund['provider_refund_id']) && !empty($providerData['id'])) {
        $set[] = "provider_refund_id = '" . mysqli_real_escape_string($conn, (string) $providerData['id']) . "'";
      }

      $new = $current;
      $amount = (int) $refund['amount'];
      $userId = (int) $refund['user_id'];
      if ($providerStatus === 'processed') {
        $new = 'refunded';
        $set[] = "completed_at = NOW()";
        if (!empty($refund['debit_ledger_id'])) {
          mysqli_query($conn, "UPDATE wallet_ledger_entries SET status = 'posted' WHERE id = " . (int) $refund['debit_ledger_id']);
        }
      } elseif ($providerStatus === 'failed' || $providerStatus === 'reversed') {
        $new = 'failed';
        $reason = trim((string) ($providerData['message'] ?? $providerData['gateway_response'] ?? 'Paystack could not complete the refund'));
        $set[] = "failure_reason = '" . mysqli_real_escape_string($conn, substr($reason, 0, 250)) . "'";
        $set[] = "completed_at = NOW()";
        $set[] = "reversal_ledger_id = " . nvWalletRefundReturnToWallet($conn, $refund, $reason);
      } elseif ($providerStatus === 'needs-attention' || $providerStatus === 'needs_attention') {
        $new = 'needs_attention';
      } elseif (in_array($providerStatus, ['pending', 'processing'], true)) {
        $new = 'processing';
      }
      $set[] = "status = '$new'";
      mysqli_query($conn, "UPDATE wallet_deposit_refunds SET " . implode(', ', $set) . " WHERE id = $refundId");

      if ($new !== $current && $new === 'refunded') {
        nvWalletRefundNotify($conn, $userId, 'Refund sent to your bank',
          'Your refund of ' . nvWalletRefundMoney($amount) . ' has been sent back to the account you paid from. It can take a few business days to show.',
          ['refund_reference' => $refund['reference']]);
      } elseif ($new !== $current && $new === 'failed') {
        nvWalletRefundNotify($conn, $userId, 'Refund could not be completed',
          'We could not send your refund of ' . nvWalletRefundMoney($amount) . ' to your bank, so it is back in your Nivasity wallet. Contact support if you still need it refunded.',
          ['refund_reference' => $refund['reference']]);
      }

      mysqli_commit($conn);
      return $new;
    } catch (Throwable $e) {
      mysqli_rollback($conn);
      throw $e;
    }
  }
}
