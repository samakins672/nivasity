<?php

if (!function_exists('bulk_material_payment_has_column')) {
  function bulk_material_payment_has_column(mysqli $conn, string $table, string $column): bool
  {
    static $cache = [];

    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) {
      return $cache[$key];
    }

    $safeTable = mysqli_real_escape_string($conn, $table);
    $safeColumn = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    $cache[$key] = $result && mysqli_num_rows($result) > 0;

    return $cache[$key];
  }
}

if (!function_exists('bulk_material_payment_has_table')) {
  function bulk_material_payment_has_table(mysqli $conn, string $table): bool
  {
    static $cache = [];

    $key = strtolower($table);
    if (array_key_exists($key, $cache)) {
      return $cache[$key];
    }

    $safeTable = mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '{$safeTable}'");
    $cache[$key] = $result && mysqli_num_rows($result) > 0;

    return $cache[$key];
  }
}

if (!function_exists('bulk_material_payment_ensure_schema')) {
  function bulk_material_payment_ensure_schema(mysqli $conn): bool
  {
    static $schemaReady = false;

    if ($schemaReady) {
      return true;
    }

    $createBatchesSql = "CREATE TABLE IF NOT EXISTS `manual_bulk_payment_batches` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `ref_id` varchar(64) NOT NULL,
      `manual_id` int(11) NOT NULL,
      `school_id` int(11) NOT NULL DEFAULT 1,
      `payer_user_id` int(11) NOT NULL,
      `payer_dept_id` int(11) NOT NULL DEFAULT 0,
      `manual_seller_id` int(11) DEFAULT NULL,
      `student_count` int(11) NOT NULL DEFAULT 0,
      `subtotal` int(11) NOT NULL DEFAULT 0,
      `fee_percent` decimal(5,2) NOT NULL DEFAULT 5.00,
      `fee_amount` int(11) NOT NULL DEFAULT 0,
      `total_amount` int(11) NOT NULL DEFAULT 0,
      `payment_status` varchar(32) NOT NULL DEFAULT 'pending',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `paid_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_manual_bulk_payment_ref` (`ref_id`),
      KEY `idx_manual_bulk_payment_payer_created` (`payer_user_id`, `created_at`),
      KEY `idx_manual_bulk_payment_manual_status` (`manual_id`, `payment_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mysqli_query($conn, $createBatchesSql);

    $createStudentsSql = "CREATE TABLE IF NOT EXISTS `manual_bulk_payment_students` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `batch_id` int(11) NOT NULL,
      `ref_id` varchar(64) NOT NULL,
      `manual_id` int(11) NOT NULL,
      `school_id` int(11) NOT NULL DEFAULT 1,
      `payer_user_id` int(11) NOT NULL,
      `payer_dept_id` int(11) NOT NULL DEFAULT 0,
      `placeholder_user_id` int(11) DEFAULT NULL,
      `matched_user_id` int(11) DEFAULT NULL,
      `manuals_bought_id` int(11) DEFAULT NULL,
      `first_name` varchar(255) NOT NULL,
      `last_name` varchar(255) NOT NULL,
      `normalized_first_name` varchar(255) NOT NULL,
      `normalized_last_name` varchar(255) NOT NULL,
      `raw_matric_no` varchar(100) NOT NULL,
      `normalized_matric_no` varchar(100) NOT NULL,
      `pending_lookup_matric_no` varchar(100) NOT NULL,
      `claim_status` varchar(32) NOT NULL DEFAULT 'pending',
      `claimed_at` datetime DEFAULT NULL,
      `confirmed_at` datetime DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_manual_bulk_payment_student` (`manual_id`, `school_id`, `normalized_matric_no`, `normalized_first_name`, `normalized_last_name`, `claim_status`),
      KEY `idx_manual_bulk_payment_ref` (`ref_id`),
      KEY `idx_manual_bulk_payment_batch` (`batch_id`),
      KEY `idx_manual_bulk_payment_match` (`school_id`, `payer_dept_id`, `normalized_matric_no`, `normalized_first_name`, `normalized_last_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mysqli_query($conn, $createStudentsSql);

    if (!bulk_material_payment_has_column($conn, 'manuals_bought', 'payer_user_id')) {
      mysqli_query($conn, "ALTER TABLE `manuals_bought` ADD COLUMN `payer_user_id` int(11) DEFAULT NULL AFTER `buyer`");
    }

    $schemaReady = bulk_material_payment_has_table($conn, 'manual_bulk_payment_batches')
      && bulk_material_payment_has_table($conn, 'manual_bulk_payment_students')
      && bulk_material_payment_has_column($conn, 'manuals_bought', 'payer_user_id');

    return $schemaReady;
  }
}

if (!function_exists('bulk_material_payment_normalize_text')) {
  function bulk_material_payment_normalize_text(string $value): string
  {
    $value = trim($value);
    if ($value === '') {
      return '';
    }

    $value = preg_replace('/\s+/', ' ', $value);
    return strtolower(trim((string) $value));
  }
}

if (!function_exists('bulk_material_payment_pending_lookup_matric')) {
  function bulk_material_payment_pending_lookup_matric(string $matricNo): string
  {
    $normalized = bulk_material_payment_normalize_text($matricNo);
    if ($normalized === '') {
      return '';
    }

    return $normalized . '_pend_';
  }
}

if (!function_exists('bulk_material_payment_fee_breakdown')) {
  function bulk_material_payment_fee_breakdown(int $subtotal, float $feePercent = 5.0): array
  {
    $subtotal = max(0, $subtotal);
    $feePercent = max(0, $feePercent);
    $feeAmount = (int) round($subtotal * ($feePercent / 100));

    return [
      'subtotal' => $subtotal,
      'fee_percent' => $feePercent,
      'fee_amount' => $feeAmount,
      'total_amount' => $subtotal + $feeAmount,
    ];
  }
}

if (!function_exists('bulk_material_payment_format_csv_header')) {
  function bulk_material_payment_format_csv_header(): array
  {
    return ['first_name', 'last_name', 'matric_no'];
  }
}

if (!function_exists('bulk_material_payment_placeholder_status')) {
  function bulk_material_payment_placeholder_status(): string
  {
    return 'pending_bulk_claim';
  }
}

if (!function_exists('bulk_material_payment_claim_status_pending')) {
  function bulk_material_payment_claim_status_pending(): string
  {
    return 'pending';
  }
}

if (!function_exists('bulk_material_payment_claim_status_awaiting_student_confirmation')) {
  function bulk_material_payment_claim_status_awaiting_student_confirmation(): string
  {
    return 'awaiting_student_confirmation';
  }
}

if (!function_exists('bulk_material_payment_claim_status_awaiting_claim_confirmation')) {
  function bulk_material_payment_claim_status_awaiting_claim_confirmation(): string
  {
    return 'awaiting_claim_confirmation';
  }
}

if (!function_exists('bulk_material_payment_transaction_context')) {
  function bulk_material_payment_transaction_context(): string
  {
    return 'bulk_material_purchase';
  }
}

if (!function_exists('bulk_material_payment_generate_ref')) {
  function bulk_material_payment_generate_ref(int $payerUserId): string
  {
    try {
      $suffix = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
      $suffix = substr(md5(uniqid((string) $payerUserId, true)), 0, 8);
    }

    return 'bulk_mat_' . $payerUserId . '_' . time() . '_' . $suffix;
  }
}

if (!function_exists('bulk_material_payment_placeholder_email')) {
  function bulk_material_payment_placeholder_email(int $schoolId, int $deptId, string $normalizedMatricNo, string $normalizedFirstName, string $normalizedLastName): string
  {
    $hash = sha1($schoolId . '|' . $deptId . '|' . $normalizedMatricNo . '|' . $normalizedFirstName . '|' . $normalizedLastName);
    return 'bulk.pending.' . substr($hash, 0, 20) . '@pending.nivasity.local';
  }
}

if (!function_exists('bulk_material_payment_create_manual_purchase')) {
  function bulk_material_payment_create_manual_purchase(mysqli $conn, int $manualId, int $price, int $sellerId, int $buyerId, int $payerUserId, string $refId, int $schoolId): int
  {
    if ($manualId <= 0 || $price < 0 || $buyerId <= 0 || $schoolId <= 0 || $refId === '') {
      throw new Exception('Incomplete bulk material purchase payload.');
    }

    $existingRs = mysqli_query(
      $conn,
      "SELECT id
       FROM manuals_bought
       WHERE ref_id = '" . mysqli_real_escape_string($conn, $refId) . "'
         AND manual_id = {$manualId}
         AND buyer = {$buyerId}
       LIMIT 1"
    );
    if ($existingRs && mysqli_num_rows($existingRs) > 0) {
      $existingRow = mysqli_fetch_assoc($existingRs) ?: [];
      return (int) ($existingRow['id'] ?? 0);
    }

    $insertSql = "INSERT INTO manuals_bought (manual_id, price, seller, buyer, payer_user_id, ref_id, status, school_id)
                  VALUES ({$manualId}, {$price}, {$sellerId}, {$buyerId}, {$payerUserId}, '" . mysqli_real_escape_string($conn, $refId) . "', 'successful', {$schoolId})";
    if (!mysqli_query($conn, $insertSql)) {
      throw new Exception('Unable to create the bulk material purchase row: ' . mysqli_error($conn));
    }

    return (int) mysqli_insert_id($conn);
  }
}

if (!function_exists('bulk_material_payment_find_matching_user')) {
  function bulk_material_payment_find_matching_user(mysqli $conn, int $schoolId, int $deptId, string $normalizedMatricNo, string $normalizedFirstName, string $normalizedLastName): ?array
  {
    if ($schoolId <= 0 || $deptId <= 0 || $normalizedMatricNo === '' || $normalizedFirstName === '' || $normalizedLastName === '') {
      return null;
    }

    $matricSafe = mysqli_real_escape_string($conn, $normalizedMatricNo);
    $firstSafe = mysqli_real_escape_string($conn, $normalizedFirstName);
    $lastSafe = mysqli_real_escape_string($conn, $normalizedLastName);
    $placeholderStatusSafe = mysqli_real_escape_string($conn, bulk_material_payment_placeholder_status());

    $query = mysqli_query(
      $conn,
      "SELECT *
       FROM users
       WHERE school = {$schoolId}
         AND dept = {$deptId}
         AND LOWER(TRIM(matric_no)) = '{$matricSafe}'
         AND LOWER(TRIM(first_name)) = '{$firstSafe}'
         AND LOWER(TRIM(last_name)) = '{$lastSafe}'
         AND status <> '{$placeholderStatusSafe}'
       ORDER BY CASE WHEN status = 'verified' THEN 0 ELSE 1 END, id DESC
       LIMIT 1"
    );

    if ($query && mysqli_num_rows($query) > 0) {
      return mysqli_fetch_assoc($query) ?: null;
    }

    return null;
  }
}

if (!function_exists('bulk_material_payment_find_or_create_placeholder_user')) {
  function bulk_material_payment_find_or_create_placeholder_user(mysqli $conn, array $payload): int
  {
    $schoolId = (int) ($payload['school_id'] ?? 0);
    $deptId = (int) ($payload['dept_id'] ?? 0);
    $firstName = trim((string) ($payload['first_name'] ?? ''));
    $lastName = trim((string) ($payload['last_name'] ?? ''));
    $normalizedFirstName = bulk_material_payment_normalize_text((string) ($payload['normalized_first_name'] ?? $firstName));
    $normalizedLastName = bulk_material_payment_normalize_text((string) ($payload['normalized_last_name'] ?? $lastName));
    $pendingLookupMatric = trim((string) ($payload['pending_lookup_matric_no'] ?? ''));

    if ($schoolId <= 0 || $deptId <= 0 || $normalizedFirstName === '' || $normalizedLastName === '' || $pendingLookupMatric === '') {
      throw new Exception('Incomplete placeholder student payload.');
    }

    $pendingMatricSafe = mysqli_real_escape_string($conn, bulk_material_payment_normalize_text($pendingLookupMatric));
    $firstSafe = mysqli_real_escape_string($conn, $normalizedFirstName);
    $lastSafe = mysqli_real_escape_string($conn, $normalizedLastName);
    $statusSafe = mysqli_real_escape_string($conn, bulk_material_payment_placeholder_status());

    $existingQuery = mysqli_query(
      $conn,
      "SELECT id
       FROM users
       WHERE school = {$schoolId}
         AND dept = {$deptId}
         AND status = '{$statusSafe}'
         AND LOWER(TRIM(matric_no)) = '{$pendingMatricSafe}'
         AND LOWER(TRIM(first_name)) = '{$firstSafe}'
         AND LOWER(TRIM(last_name)) = '{$lastSafe}'
       ORDER BY id DESC
       LIMIT 1"
    );
    if ($existingQuery && mysqli_num_rows($existingQuery) > 0) {
      $existingRow = mysqli_fetch_assoc($existingQuery) ?: [];
      return (int) ($existingRow['id'] ?? 0);
    }

    $email = bulk_material_payment_placeholder_email($schoolId, $deptId, bulk_material_payment_normalize_text((string) ($payload['normalized_matric_no'] ?? '')), $normalizedFirstName, $normalizedLastName);
    $emailSafe = mysqli_real_escape_string($conn, $email);
    $firstNameSafe = mysqli_real_escape_string($conn, $firstName);
    $lastNameSafe = mysqli_real_escape_string($conn, $lastName);
    $matricSafe = mysqli_real_escape_string($conn, $pendingLookupMatric);
    $passwordSafe = mysqli_real_escape_string($conn, md5(bin2hex(random_bytes(16))));

    $insertSql = "INSERT INTO users (first_name, last_name, email, phone, password, role, school, dept, matric_no, status)
                  VALUES ('{$firstNameSafe}', '{$lastNameSafe}', '{$emailSafe}', '00000000000', '{$passwordSafe}', 'student', {$schoolId}, {$deptId}, '{$matricSafe}', '{$statusSafe}')";
    if (!mysqli_query($conn, $insertSql)) {
      throw new Exception('Unable to create a pending placeholder student: ' . mysqli_error($conn));
    }

    return (int) mysqli_insert_id($conn);
  }
}

if (!function_exists('bulk_material_payment_process_wallet_batch')) {
  function bulk_material_payment_process_wallet_batch(mysqli $conn, array $payer, array $manual, array $rows, string $walletPin, string $sourceChannel = 'web'): array
  {
    if (!bulk_material_payment_ensure_schema($conn)) {
      throw new Exception('Bulk payment tables are not ready yet.');
    }

    $payerUserId = (int) ($payer['id'] ?? 0);
    $schoolId = (int) ($payer['school'] ?? 0);
    $payerDeptId = (int) ($payer['dept'] ?? 0);
    $manualId = (int) ($manual['id'] ?? 0);
    $manualPrice = (int) round((float) ($manual['price'] ?? 0));
    $manualSellerId = (int) ($manual['user_id'] ?? 0);

    if ($payerUserId <= 0 || $schoolId <= 0 || $payerDeptId <= 0 || $manualId <= 0 || $manualPrice <= 0) {
      throw new Exception('Bulk payment request is incomplete.');
    }
    if (empty($rows)) {
      throw new Exception('Add at least one valid student before paying.');
    }
    if (!function_exists('nivasityVerifyWalletPin') || !function_exists('nivasityGetUserWallet') || !function_exists('nivasityRecordSchoolPayable')) {
      throw new Exception('Wallet payment helpers are not available right now.');
    }

    nivasityVerifyWalletPin($conn, $payerUserId, $walletPin);

    $refId = bulk_material_payment_generate_ref($payerUserId);
    $refIdSafe = mysqli_real_escape_string($conn, $refId);
    $breakdown = bulk_material_payment_fee_breakdown(count($rows) * $manualPrice, 5.0);
    $subtotal = (int) ($breakdown['subtotal'] ?? 0);
    $feeAmount = (int) ($breakdown['fee_amount'] ?? 0);
    $totalAmount = (int) ($breakdown['total_amount'] ?? 0);
    $studentCount = count($rows);
    $sourceChannel = strtolower(trim((string) $sourceChannel)) ?: 'web';
    $placeholderStatus = bulk_material_payment_placeholder_status();
    $pendingClaimStatus = bulk_material_payment_claim_status_awaiting_claim_confirmation();
    $pendingStudentStatus = bulk_material_payment_claim_status_awaiting_student_confirmation();
    $transactionContext = bulk_material_payment_transaction_context();

    mysqli_begin_transaction($conn);
    try {
      $walletLockSql = "SELECT id, school_id, balance, status FROM user_wallets WHERE user_id = {$payerUserId} LIMIT 1 FOR UPDATE";
      $walletLockRs = mysqli_query($conn, $walletLockSql);
      if (!$walletLockRs || mysqli_num_rows($walletLockRs) < 1) {
        throw new Exception('Create your wallet before starting a bulk payment.');
      }

      $walletRow = mysqli_fetch_assoc($walletLockRs) ?: [];
      if ((string) ($walletRow['status'] ?? 'active') !== 'active') {
        throw new Exception('Your wallet is not active right now.');
      }

      $walletId = (int) ($walletRow['id'] ?? 0);
      $balanceBefore = (int) ($walletRow['balance'] ?? 0);
      if ($balanceBefore < $totalAmount) {
        throw new Exception('Insufficient wallet balance for this bulk payment.');
      }

      $batchInsertSql = "INSERT INTO manual_bulk_payment_batches (
          ref_id, manual_id, school_id, payer_user_id, payer_dept_id, manual_seller_id,
          student_count, subtotal, fee_percent, fee_amount, total_amount, payment_status, paid_at
        ) VALUES (
          '{$refIdSafe}', {$manualId}, {$schoolId}, {$payerUserId}, {$payerDeptId}, {$manualSellerId},
          {$studentCount}, {$subtotal}, 5.00, {$feeAmount}, {$totalAmount}, 'successful', NOW()
        )";
      if (!mysqli_query($conn, $batchInsertSql)) {
        throw new Exception('Unable to create the bulk payment batch: ' . mysqli_error($conn));
      }

      $batchId = (int) mysqli_insert_id($conn);
      foreach ($rows as $row) {
        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName = trim((string) ($row['last_name'] ?? ''));
        $rawMatricNo = trim((string) ($row['matric_no'] ?? ''));
        $normalizedFirstName = bulk_material_payment_normalize_text((string) ($row['normalized_first_name'] ?? $firstName));
        $normalizedLastName = bulk_material_payment_normalize_text((string) ($row['normalized_last_name'] ?? $lastName));
        $normalizedMatricNo = bulk_material_payment_normalize_text((string) ($row['normalized_matric_no'] ?? $rawMatricNo));

        if ($normalizedFirstName === '' || $normalizedLastName === '' || $normalizedMatricNo === '') {
          throw new Exception('A valid student row is missing first name, last name, or matric number.');
        }

        $matchedUser = bulk_material_payment_find_matching_user($conn, $schoolId, $payerDeptId, $normalizedMatricNo, $normalizedFirstName, $normalizedLastName);
        $matchedUserId = (int) ($matchedUser['id'] ?? 0);
        $placeholderUserId = 0;
        $manualsBoughtId = 0;
        $claimStatus = $matchedUserId > 0 ? $pendingStudentStatus : $pendingClaimStatus;

        $pendingMatric = bulk_material_payment_pending_lookup_matric($normalizedMatricNo);
        if ($matchedUserId <= 0) {
          $placeholderUserId = bulk_material_payment_find_or_create_placeholder_user($conn, [
            'school_id' => $schoolId,
            'dept_id' => $payerDeptId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'normalized_first_name' => $normalizedFirstName,
            'normalized_last_name' => $normalizedLastName,
            'normalized_matric_no' => $normalizedMatricNo,
            'pending_lookup_matric_no' => $pendingMatric,
          ]);
          $manualsBoughtId = bulk_material_payment_create_manual_purchase(
            $conn,
            $manualId,
            $manualPrice,
            $manualSellerId,
            $placeholderUserId,
            $payerUserId,
            $refId,
            $schoolId
          );
        }

        $studentExistsSql = "SELECT id FROM manual_bulk_payment_students
          WHERE manual_id = {$manualId}
            AND school_id = {$schoolId}
            AND payer_dept_id = {$payerDeptId}
            AND normalized_matric_no = '" . mysqli_real_escape_string($conn, $normalizedMatricNo) . "'
            AND normalized_first_name = '" . mysqli_real_escape_string($conn, $normalizedFirstName) . "'
            AND normalized_last_name = '" . mysqli_real_escape_string($conn, $normalizedLastName) . "'
            AND claim_status IN ('pending', 'awaiting_claim_confirmation', 'awaiting_student_confirmation')
          LIMIT 1 FOR UPDATE";
        $studentExistsRs = mysqli_query($conn, $studentExistsSql);
        if (!$studentExistsRs) {
          throw new Exception('Unable to validate bulk student state: ' . mysqli_error($conn));
        }
        if (mysqli_num_rows($studentExistsRs) > 0) {
          throw new Exception('One of the selected students already has a pending bulk-payment claim for this material.');
        }

        $studentInsertSql = "INSERT INTO manual_bulk_payment_students (
            batch_id, ref_id, manual_id, school_id, payer_user_id, payer_dept_id,
            placeholder_user_id, matched_user_id, manuals_bought_id, first_name, last_name,
            normalized_first_name, normalized_last_name, raw_matric_no, normalized_matric_no,
            pending_lookup_matric_no, claim_status
          ) VALUES (
            {$batchId}, '{$refIdSafe}', {$manualId}, {$schoolId}, {$payerUserId}, {$payerDeptId},
            " . ($placeholderUserId > 0 ? $placeholderUserId : 'NULL') . ", " . ($matchedUserId > 0 ? $matchedUserId : 'NULL') . ", " . ($manualsBoughtId > 0 ? $manualsBoughtId : 'NULL') . ",
            '" . mysqli_real_escape_string($conn, $firstName) . "',
            '" . mysqli_real_escape_string($conn, $lastName) . "',
            '" . mysqli_real_escape_string($conn, $normalizedFirstName) . "',
            '" . mysqli_real_escape_string($conn, $normalizedLastName) . "',
            '" . mysqli_real_escape_string($conn, $rawMatricNo) . "',
            '" . mysqli_real_escape_string($conn, $normalizedMatricNo) . "',
            '" . mysqli_real_escape_string($conn, $pendingMatric) . "',
            '" . mysqli_real_escape_string($conn, $claimStatus) . "'
          )";
        if (!mysqli_query($conn, $studentInsertSql)) {
          throw new Exception('Unable to save a student in the bulk batch: ' . mysqli_error($conn));
        }
      }

      $balanceAfter = $balanceBefore - $totalAmount;
      $metadataSafe = mysqli_real_escape_string($conn, json_encode([
        'source_ref_id' => $refId,
        'source_channel' => $sourceChannel,
        'manual_id' => $manualId,
        'student_count' => $studentCount,
        'transaction_context' => $transactionContext,
      ]));
      $ledgerReference = mysqli_real_escape_string($conn, 'wallet_bulk_purchase:' . $refId);
      $descriptionSafe = mysqli_real_escape_string($conn, 'Bulk material wallet purchase');

      $ledgerInsertSql = "INSERT INTO wallet_ledger_entries (
          wallet_id, entry_type, amount, balance_before, balance_after, status,
          reference, provider_reference, description, metadata
        ) VALUES (
          {$walletId}, 'debit', {$totalAmount}, {$balanceBefore}, {$balanceAfter}, 'posted',
          '{$ledgerReference}', '{$refIdSafe}', '{$descriptionSafe}', '{$metadataSafe}'
        )";
      if (!mysqli_query($conn, $ledgerInsertSql)) {
        throw new Exception('Failed to record wallet debit: ' . mysqli_error($conn));
      }

      $walletUpdateSql = "UPDATE user_wallets SET balance = {$balanceAfter}, updated_at = NOW() WHERE id = {$walletId}";
      if (!mysqli_query($conn, $walletUpdateSql)) {
        throw new Exception('Failed to debit wallet balance: ' . mysqli_error($conn));
      }

      $txInsertSql = "INSERT INTO transactions (
          ref_id, user_id, amount, charge, profit, refund, status, medium, payment_channel, transaction_context
        ) VALUES (
          '{$refIdSafe}', {$payerUserId}, {$totalAmount}, {$feeAmount}, {$feeAmount}, 0,
          'successful', 'NIVASITY', 'wallet', '" . mysqli_real_escape_string($conn, $transactionContext) . "'
        )";
      if (!mysqli_query($conn, $txInsertSql)) {
        throw new Exception('Failed to record the bulk payment transaction: ' . mysqli_error($conn));
      }

      nivasityRecordSchoolPayable($conn, [
        'school_id' => $schoolId,
        'source_ref_id' => $refId,
        'payer_user_id' => $payerUserId,
        'source_medium' => 'NIVASITY',
        'source_channel' => $sourceChannel,
        'item_subtotal' => $subtotal,
        'collected_total' => $totalAmount,
        'charge_amount' => $feeAmount,
        'refund_amount' => 0,
        'metadata' => [
          'handler' => 'bulk_material_payment_process_wallet_batch',
          'payment_channel' => 'wallet',
          'transaction_context' => $transactionContext,
          'manual_id' => $manualId,
          'student_count' => $studentCount,
        ],
      ]);

      mysqli_commit($conn);

      if (function_exists('nivasitySendWalletAlert')) {
        nivasitySendWalletAlert($conn, $payerUserId, 'debit', [
          'amount' => $totalAmount,
          'balance_after' => $balanceAfter,
          'reference' => $refId,
          'description' => 'Bulk material wallet payment for ' . ($manual['course_code'] ?? 'material'),
        ]);
      }

      return [
        'status' => 'success',
        'ref_id' => $refId,
        'batch_id' => $batchId,
        'student_count' => $studentCount,
        'subtotal' => $subtotal,
        'fee_amount' => $feeAmount,
        'total_amount' => $totalAmount,
        'wallet_balance_after' => $balanceAfter,
      ];
    } catch (Throwable $e) {
      mysqli_rollback($conn);
      throw $e;
    }
  }
}

if (!function_exists('bulk_material_payment_get_pending_claims_for_user')) {
  function bulk_material_payment_get_pending_claims_for_user(mysqli $conn, array $user, int $limit = 5): array
  {
    $userId = (int) ($user['id'] ?? 0);
    $schoolId = (int) ($user['school'] ?? 0);
    $deptId = (int) ($user['dept'] ?? 0);
    $normalizedMatricNo = bulk_material_payment_normalize_text((string) ($user['matric_no'] ?? ''));
    $normalizedFirstName = bulk_material_payment_normalize_text((string) ($user['first_name'] ?? ''));
    $normalizedLastName = bulk_material_payment_normalize_text((string) ($user['last_name'] ?? ''));
    $limit = max(1, min(20, $limit));

    if ($userId <= 0 || $schoolId <= 0 || $deptId <= 0 || $normalizedMatricNo === '' || $normalizedFirstName === '' || $normalizedLastName === '') {
      return [];
    }
    if (!bulk_material_payment_has_table($conn, 'manual_bulk_payment_students') || !bulk_material_payment_has_table($conn, 'manual_bulk_payment_batches')) {
      return [];
    }

    $awaitingStudent = mysqli_real_escape_string($conn, bulk_material_payment_claim_status_awaiting_student_confirmation());
    $awaitingClaim = mysqli_real_escape_string($conn, bulk_material_payment_claim_status_awaiting_claim_confirmation());
    $matricSafe = mysqli_real_escape_string($conn, $normalizedMatricNo);
    $firstSafe = mysqli_real_escape_string($conn, $normalizedFirstName);
    $lastSafe = mysqli_real_escape_string($conn, $normalizedLastName);

    $query = mysqli_query(
      $conn,
      "SELECT
          s.id,
          s.batch_id,
          s.manual_id,
          s.ref_id,
          s.first_name,
          s.last_name,
          s.raw_matric_no,
          s.claim_status,
          s.created_at,
          b.payer_user_id,
          b.student_count,
          b.subtotal,
          b.total_amount,
          b.paid_at,
          b.payment_status,
          m.title,
          m.course_code,
          p.first_name AS payer_first_name,
          p.last_name AS payer_last_name
       FROM manual_bulk_payment_students AS s
       INNER JOIN manual_bulk_payment_batches AS b ON b.id = s.batch_id
       INNER JOIN manuals AS m ON m.id = s.manual_id
       INNER JOIN users AS p ON p.id = b.payer_user_id
       WHERE s.school_id = {$schoolId}
         AND s.payer_dept_id = {$deptId}
         AND b.payment_status = 'successful'
         AND (
           (s.claim_status = '{$awaitingStudent}' AND s.matched_user_id = {$userId})
           OR
           (
             s.claim_status = '{$awaitingClaim}'
             AND s.normalized_matric_no = '{$matricSafe}'
             AND s.normalized_first_name = '{$firstSafe}'
             AND s.normalized_last_name = '{$lastSafe}'
           )
         )
       ORDER BY COALESCE(b.paid_at, s.created_at) ASC, s.id ASC
       LIMIT {$limit}"
    );

    if (!$query) {
      return [];
    }

    $claims = [];
    while ($row = mysqli_fetch_assoc($query)) {
      $payerName = trim((string) ($row['payer_first_name'] ?? '') . ' ' . (string) ($row['payer_last_name'] ?? ''));
      $claims[] = [
        'id' => (int) ($row['id'] ?? 0),
        'batch_id' => (int) ($row['batch_id'] ?? 0),
        'manual_id' => (int) ($row['manual_id'] ?? 0),
        'ref_id' => (string) ($row['ref_id'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'course_code' => (string) ($row['course_code'] ?? ''),
        'student_name' => trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
        'student_matric_no' => (string) ($row['raw_matric_no'] ?? ''),
        'claim_status' => (string) ($row['claim_status'] ?? ''),
        'payer_name' => $payerName,
        'paid_at' => (string) ($row['paid_at'] ?? ''),
        'student_count' => (int) ($row['student_count'] ?? 0),
        'subtotal' => (int) ($row['subtotal'] ?? 0),
        'total_amount' => (int) ($row['total_amount'] ?? 0),
      ];
    }

    return $claims;
  }
}

if (!function_exists('bulk_material_payment_resolve_claim_for_user')) {
  function bulk_material_payment_resolve_claim_for_user(mysqli $conn, int $studentRowId, array $user, string $decision): array
  {
    $studentRowId = (int) $studentRowId;
    $userId = (int) ($user['id'] ?? 0);
    $schoolId = (int) ($user['school'] ?? 0);
    $deptId = (int) ($user['dept'] ?? 0);
    $normalizedMatricNo = bulk_material_payment_normalize_text((string) ($user['matric_no'] ?? ''));
    $normalizedFirstName = bulk_material_payment_normalize_text((string) ($user['first_name'] ?? ''));
    $normalizedLastName = bulk_material_payment_normalize_text((string) ($user['last_name'] ?? ''));
    $decision = strtolower(trim($decision));

    if ($studentRowId <= 0 || $userId <= 0 || $schoolId <= 0 || $deptId <= 0 || $normalizedMatricNo === '' || $normalizedFirstName === '' || $normalizedLastName === '') {
      throw new Exception('This account is not ready to review pending bulk claims.');
    }
    if (!in_array($decision, ['confirm', 'reject'], true)) {
      throw new Exception('Invalid bulk claim action.');
    }

    $awaitingStudent = mysqli_real_escape_string($conn, bulk_material_payment_claim_status_awaiting_student_confirmation());
    $awaitingClaim = mysqli_real_escape_string($conn, bulk_material_payment_claim_status_awaiting_claim_confirmation());
    $confirmedStatus = mysqli_real_escape_string($conn, 'confirmed');
    $rejectedStatus = mysqli_real_escape_string($conn, 'student_rejected');
    $matricSafe = mysqli_real_escape_string($conn, $normalizedMatricNo);
    $firstSafe = mysqli_real_escape_string($conn, $normalizedFirstName);
    $lastSafe = mysqli_real_escape_string($conn, $normalizedLastName);

    mysqli_begin_transaction($conn);
    try {
      $query = mysqli_query(
        $conn,
        "SELECT
            s.*,
            b.payer_user_id,
            b.student_count,
            b.subtotal,
            b.payment_status,
            m.user_id AS manual_seller_id
         FROM manual_bulk_payment_students AS s
         INNER JOIN manual_bulk_payment_batches AS b ON b.id = s.batch_id
         INNER JOIN manuals AS m ON m.id = s.manual_id
         WHERE s.id = {$studentRowId}
           AND s.school_id = {$schoolId}
           AND s.payer_dept_id = {$deptId}
           AND b.payment_status = 'successful'
         LIMIT 1 FOR UPDATE"
      );
      if (!$query || mysqli_num_rows($query) < 1) {
        throw new Exception('Pending bulk claim not found.');
      }

      $row = mysqli_fetch_assoc($query) ?: [];
      $claimStatus = (string) ($row['claim_status'] ?? '');
      $matchedUserId = (int) ($row['matched_user_id'] ?? 0);
      $identityMatches = (string) ($row['normalized_matric_no'] ?? '') === $normalizedMatricNo
        && (string) ($row['normalized_first_name'] ?? '') === $normalizedFirstName
        && (string) ($row['normalized_last_name'] ?? '') === $normalizedLastName;
      $isEligible = ($claimStatus === bulk_material_payment_claim_status_awaiting_student_confirmation() && $matchedUserId === $userId)
        || ($claimStatus === bulk_material_payment_claim_status_awaiting_claim_confirmation() && $identityMatches);

      if (!$isEligible) {
        throw new Exception('This bulk claim is not assigned to this account.');
      }

      if ($decision === 'reject') {
        $rejectSql = "UPDATE manual_bulk_payment_students
                      SET claim_status = '{$rejectedStatus}',
                          matched_user_id = {$userId},
                          updated_at = NOW()
                      WHERE id = {$studentRowId} LIMIT 1";
        if (!mysqli_query($conn, $rejectSql)) {
          throw new Exception('Unable to reject this bulk claim right now.');
        }

        mysqli_commit($conn);
        return [
          'status' => 'success',
          'message' => 'The bulk claim was rejected. Support can review it if needed.',
        ];
      }

      $manualId = (int) ($row['manual_id'] ?? 0);
      $payerUserId = (int) ($row['payer_user_id'] ?? 0);
      $placeholderUserId = (int) ($row['placeholder_user_id'] ?? 0);
      $studentCount = max(1, (int) ($row['student_count'] ?? 1));
      $unitPrice = (int) round(((float) ($row['subtotal'] ?? 0)) / $studentCount);
      $existingBoughtId = (int) ($row['manuals_bought_id'] ?? 0);
      if ($existingBoughtId <= 0) {
        $existingPurchaseRs = mysqli_query(
          $conn,
          "SELECT id FROM manuals_bought WHERE ref_id = '" . mysqli_real_escape_string($conn, (string) ($row['ref_id'] ?? '')) . "' AND manual_id = {$manualId} AND buyer = {$userId} LIMIT 1"
        );
        if ($existingPurchaseRs && mysqli_num_rows($existingPurchaseRs) > 0) {
          $existingPurchase = mysqli_fetch_assoc($existingPurchaseRs) ?: [];
          $existingBoughtId = (int) ($existingPurchase['id'] ?? 0);
        }
      }

      if ($existingBoughtId > 0 && $placeholderUserId > 0 && $placeholderUserId !== $userId) {
        $reassignSql = "UPDATE manuals_bought
                        SET buyer = {$userId},
                            payer_user_id = {$payerUserId}
                        WHERE id = {$existingBoughtId}
                          AND buyer = {$placeholderUserId}
                        LIMIT 1";
        if (!mysqli_query($conn, $reassignSql)) {
          throw new Exception('Unable to attach this material purchase to your account right now.');
        }
      }

      if ($existingBoughtId <= 0) {
        $existingBoughtId = bulk_material_payment_create_manual_purchase(
          $conn,
          $manualId,
          $unitPrice,
          (int) ($row['manual_seller_id'] ?? 0),
          $userId,
          $payerUserId,
          (string) ($row['ref_id'] ?? ''),
          $schoolId
        );
      }

      $confirmSql = "UPDATE manual_bulk_payment_students
                     SET claim_status = '{$confirmedStatus}',
                         matched_user_id = {$userId},
                         manuals_bought_id = {$existingBoughtId},
                         claimed_at = NOW(),
                         confirmed_at = NOW(),
                         updated_at = NOW()
                     WHERE id = {$studentRowId} LIMIT 1";
      if (!mysqli_query($conn, $confirmSql)) {
        throw new Exception('Unable to confirm this bulk claim right now.');
      }

      mysqli_commit($conn);

      return [
        'status' => 'success',
        'message' => 'Material claim confirmed successfully.',
        'manuals_bought_id' => $existingBoughtId,
      ];
    } catch (Throwable $e) {
      mysqli_rollback($conn);
      throw $e;
    }
  }
}

?>