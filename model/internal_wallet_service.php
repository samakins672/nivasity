<?php

if (!function_exists('nivasityWalletLog')) {
    function nivasityWalletLog($message, $context = []) {
        $payload = '[NIVASITY_WALLET] ' . $message;
        if (!empty($context)) {
            $payload .= ' ' . json_encode($context);
        }
        error_log($payload);
    }
}

if (!function_exists('nivasityEnsureSchoolInternalWallet')) {
    function nivasityEnsureSchoolInternalWallet($conn, $schoolId) {
        $schoolId = (int)$schoolId;
        if ($schoolId <= 0) {
            return null;
        }

        $selectSql = "SELECT * FROM school_internal_wallets WHERE school_id = $schoolId LIMIT 1";
        $selectRs = mysqli_query($conn, $selectSql);
        if ($selectRs && mysqli_num_rows($selectRs) > 0) {
            return mysqli_fetch_assoc($selectRs);
        }

        $insertSql = "INSERT INTO school_internal_wallets (school_id) VALUES ($schoolId)";
        if (!mysqli_query($conn, $insertSql)) {
            throw new Exception('Failed to create school internal wallet: ' . mysqli_error($conn));
        }

        $selectRs = mysqli_query($conn, $selectSql);
        if ($selectRs && mysqli_num_rows($selectRs) > 0) {
            return mysqli_fetch_assoc($selectRs);
        }

        throw new Exception('Unable to resolve school internal wallet after creation');
    }
}

if (!function_exists('nivasityRecordSchoolPayable')) {
    function nivasityRecordSchoolPayable($conn, $payload) {
        $schoolId = (int)($payload['school_id'] ?? 0);
        $sourceRefId = trim((string)($payload['source_ref_id'] ?? ''));
        $payerUserId = (int)($payload['payer_user_id'] ?? 0);
        $sourceMedium = strtoupper(trim((string)($payload['source_medium'] ?? 'NIVASITY')));
        $sourceChannel = strtolower(trim((string)($payload['source_channel'] ?? 'web')));
        $itemSubtotal = (int)round((float)($payload['item_subtotal'] ?? 0));
        $collectedTotal = (int)round((float)($payload['collected_total'] ?? $itemSubtotal));
        $chargeAmount = (int)round((float)($payload['charge_amount'] ?? 0));
        $refundAmount = (int)round((float)($payload['refund_amount'] ?? 0));
        $metadata = $payload['metadata'] ?? [];
        $payableAmount = max(0, $itemSubtotal - $refundAmount);

        if ($schoolId <= 0 || $payerUserId <= 0 || $sourceRefId === '') {
            return [
                'status' => 'ignored',
                'payable_amount' => 0,
            ];
        }

        $sourceRefSafe = mysqli_real_escape_string($conn, $sourceRefId);
        $existingSql = "SELECT * FROM school_payable_ledger WHERE source_ref_id = '$sourceRefSafe' LIMIT 1";
        $existingRs = mysqli_query($conn, $existingSql);
        if ($existingRs && mysqli_num_rows($existingRs) > 0) {
            $existingRow = mysqli_fetch_assoc($existingRs);
            return [
                'status' => 'exists',
                'payable_amount' => (int)($existingRow['payable_amount'] ?? 0),
                'row' => $existingRow,
            ];
        }

        nivasityEnsureSchoolInternalWallet($conn, $schoolId);

        $mediumSafe = mysqli_real_escape_string($conn, $sourceMedium);
        $channelSafe = mysqli_real_escape_string($conn, $sourceChannel);
        $metadataJson = mysqli_real_escape_string($conn, json_encode($metadata));

        $insertSql = "INSERT INTO school_payable_ledger (
                school_id, source_ref_id, payer_user_id, source_medium, source_channel,
                item_subtotal, collected_total, charge_amount, refund_amount, payable_amount, metadata
            ) VALUES (
                $schoolId, '$sourceRefSafe', $payerUserId, '$mediumSafe', '$channelSafe',
                $itemSubtotal, $collectedTotal, $chargeAmount, $refundAmount, $payableAmount, '$metadataJson'
            )";

        if (!mysqli_query($conn, $insertSql)) {
            throw new Exception('Failed to insert school payable ledger row: ' . mysqli_error($conn));
        }

        if ($payableAmount > 0) {
            $updateWalletSql = "UPDATE school_internal_wallets
                                SET current_balance = current_balance + $payableAmount,
                                    pending_payout_balance = pending_payout_balance + $payableAmount,
                                    updated_at = NOW()
                                WHERE school_id = $schoolId";
            if (!mysqli_query($conn, $updateWalletSql)) {
                throw new Exception('Failed to update school internal wallet balance: ' . mysqli_error($conn));
            }
        }

        nivasityWalletLog('Recorded school payable credit', [
            'school_id' => $schoolId,
            'source_ref_id' => $sourceRefId,
            'source_medium' => $sourceMedium,
            'payable_amount' => $payableAmount,
        ]);

        return [
            'status' => 'created',
            'payable_amount' => $payableAmount,
        ];
    }
}

if (!function_exists('nivasityGetUserWallet')) {
    function nivasityGetUserWallet($conn, $userId) {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return null;
        }

        $sql = "SELECT w.*, va.provider, va.provider_account_id, va.account_name, va.account_number, va.bank_name, va.bank_slug, va.status AS account_status
                FROM user_wallets w
                LEFT JOIN wallet_virtual_accounts va ON va.wallet_id = w.id
                WHERE w.user_id = $userId
                LIMIT 1";
        $rs = mysqli_query($conn, $sql);
        if ($rs && mysqli_num_rows($rs) > 0) {
            return mysqli_fetch_assoc($rs);
        }

        return null;
    }
}

if (!function_exists('nivasityCreateWalletOnRequest')) {
    function nivasityCreateWalletOnRequest($conn, $userId, $requestedVia = 'web') {
        $wallet = nivasityGetUserWallet($conn, $userId);
        if ($wallet) {
            return [
                'status' => 'exists',
                'wallet' => $wallet,
            ];
        }

        $userId = (int)$userId;
        $requestedVia = strtolower(trim((string)$requestedVia));
        $requestedVia = $requestedVia !== '' ? $requestedVia : 'web';

        $userSql = "SELECT id, first_name, last_name, email, phone, school, role, status FROM users WHERE id = $userId LIMIT 1";
        $userRs = mysqli_query($conn, $userSql);
        if (!$userRs || mysqli_num_rows($userRs) < 1) {
            throw new Exception('User not found for wallet creation');
        }

        $user = mysqli_fetch_assoc($userRs);
        if (!in_array($user['role'], ['student', 'hoc'], true)) {
            throw new Exception('Wallets are only available for student and hoc users');
        }
        if ((string)$user['status'] !== 'verified') {
            throw new Exception('Wallet creation requires a verified user account');
        }

        if (!defined('PAYSTACK_SECRET_KEY') || PAYSTACK_SECRET_KEY === '') {
            throw new Exception('Paystack secret key is not configured');
        }

        $payload = [
            'email' => (string)$user['email'],
            'first_name' => (string)$user['first_name'],
            'last_name' => (string)$user['last_name'],
            'phone' => (string)$user['phone'],
            'preferred_bank' => 'wema-bank',
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.paystack.co/dedicated_account',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            ],
        ]);
        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            throw new Exception('Failed to create wallet account: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if (!isset($decoded['status']) || $decoded['status'] !== true || empty($decoded['data']['account_number'])) {
            throw new Exception('Paystack DVA request failed: ' . (is_string($response) ? $response : json_encode($decoded)));
        }

        $walletData = $decoded['data'];
        $schoolId = (int)($user['school'] ?? 0);
        $requestedViaSafe = mysqli_real_escape_string($conn, $requestedVia);
        $providerAccountId = mysqli_real_escape_string($conn, (string)($walletData['id'] ?? ''));
        $providerCustomerCode = mysqli_real_escape_string($conn, (string)($walletData['customer']['customer_code'] ?? ''));
        $accountName = mysqli_real_escape_string($conn, (string)($walletData['account_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))));
        $accountNumber = mysqli_real_escape_string($conn, (string)($walletData['account_number'] ?? ''));
        $bankName = mysqli_real_escape_string($conn, (string)($walletData['bank']['name'] ?? 'Wema Bank'));
        $bankSlug = mysqli_real_escape_string($conn, (string)($walletData['bank']['slug'] ?? 'wema-bank'));
        $rawResponse = mysqli_real_escape_string($conn, json_encode($decoded));

        mysqli_begin_transaction($conn);
        try {
            $insertWalletSql = "INSERT INTO user_wallets (user_id, school_id, requested_via) VALUES ($userId, $schoolId, '$requestedViaSafe')";
            if (!mysqli_query($conn, $insertWalletSql)) {
                throw new Exception('Failed to create wallet row: ' . mysqli_error($conn));
            }
            $walletId = (int)mysqli_insert_id($conn);

            $insertVaSql = "INSERT INTO wallet_virtual_accounts (
                    wallet_id, provider, provider_account_id, provider_customer_code,
                    account_name, account_number, bank_name, bank_slug, raw_response
                ) VALUES (
                    $walletId, 'paystack', '$providerAccountId', '$providerCustomerCode',
                    '$accountName', '$accountNumber', '$bankName', '$bankSlug', '$rawResponse'
                )";
            if (!mysqli_query($conn, $insertVaSql)) {
                throw new Exception('Failed to create wallet virtual account row: ' . mysqli_error($conn));
            }

            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }

        $wallet = nivasityGetUserWallet($conn, $userId);
        nivasityWalletLog('Created wallet on explicit request', [
            'user_id' => $userId,
            'requested_via' => $requestedVia,
            'account_number' => $wallet['account_number'] ?? null,
        ]);

        return [
            'status' => 'created',
            'wallet' => $wallet,
        ];
    }
}

if (!function_exists('nivasityNormalizePaystackAmount')) {
    function nivasityNormalizePaystackAmount($amount) {
        $amount = (float)$amount;
        if ($amount <= 0) {
            return 0;
        }

        return (int)round($amount / 100);
    }
}

if (!function_exists('nivasityResolveWalletFromPaystackPayload')) {
    function nivasityResolveWalletFromPaystackPayload($conn, $data) {
        $candidates = [];

        $accountNumberCandidates = [
            $data['dedicated_account']['account_number'] ?? null,
            $data['authorization']['receiver_bank_account_number'] ?? null,
            $data['customer']['dedicated_account']['account_number'] ?? null,
        ];

        foreach ($accountNumberCandidates as $accountNumber) {
            $accountNumber = trim((string)$accountNumber);
            if ($accountNumber !== '') {
                $safe = mysqli_real_escape_string($conn, $accountNumber);
                $candidates[] = "va.account_number = '$safe'";
            }
        }

        $providerAccountCandidates = [
            $data['dedicated_account']['id'] ?? null,
            $data['customer']['dedicated_account']['id'] ?? null,
        ];

        foreach ($providerAccountCandidates as $providerAccountId) {
            $providerAccountId = trim((string)$providerAccountId);
            if ($providerAccountId !== '') {
                $safe = mysqli_real_escape_string($conn, $providerAccountId);
                $candidates[] = "va.provider_account_id = '$safe'";
            }
        }

        $customerCode = trim((string)($data['customer']['customer_code'] ?? ''));
        if ($customerCode !== '') {
            $safe = mysqli_real_escape_string($conn, $customerCode);
            $candidates[] = "va.provider_customer_code = '$safe'";
        }

        $email = trim((string)($data['customer']['email'] ?? ''));
        if ($email !== '') {
            $safeEmail = mysqli_real_escape_string($conn, $email);
            $candidates[] = "u.email = '$safeEmail'";
        }

        if (empty($candidates)) {
            return null;
        }

        $where = implode(' OR ', array_unique($candidates));
        $sql = "SELECT w.*, va.provider, va.provider_account_id, va.provider_customer_code, va.account_name, va.account_number, va.bank_name, va.bank_slug, u.email
                FROM user_wallets w
                LEFT JOIN wallet_virtual_accounts va ON va.wallet_id = w.id
                LEFT JOIN users u ON u.id = w.user_id
                WHERE $where
                LIMIT 1";
        $rs = mysqli_query($conn, $sql);
        if ($rs && mysqli_num_rows($rs) > 0) {
            return mysqli_fetch_assoc($rs);
        }

        return null;
    }
}

if (!function_exists('nivasityApplyWalletFundingTransaction')) {
    function nivasityApplyWalletFundingTransaction($conn, $wallet, $data, $source = 'webhook', $providerEvent = null) {
        $walletId = (int)($wallet['id'] ?? 0);
        $userId = (int)($wallet['user_id'] ?? 0);
        if ($walletId <= 0 || $userId <= 0) {
            throw new Exception('Invalid wallet supplied for funding application');
        }

        $providerReference = trim((string)($data['reference'] ?? $data['transaction_reference'] ?? $data['id'] ?? ''));
        if ($providerReference === '') {
            throw new Exception('Unable to resolve provider reference for wallet funding');
        }

        $providerTransactionId = trim((string)($data['id'] ?? ''));
        $providerAccountId = trim((string)($data['dedicated_account']['id'] ?? $wallet['provider_account_id'] ?? ''));
        $accountNumber = trim((string)($data['dedicated_account']['account_number'] ?? $data['authorization']['receiver_bank_account_number'] ?? $wallet['account_number'] ?? ''));
        $amount = nivasityNormalizePaystackAmount($data['amount'] ?? 0);
        $description = trim((string)($data['narration'] ?? $data['gateway_response'] ?? 'Wallet funding via Paystack DVA'));
        $source = strtolower(trim((string)$source));
        $source = $source !== '' ? $source : 'webhook';
        $providerEvent = trim((string)$providerEvent);
        $rawPayload = json_encode($data);

        if ($amount <= 0) {
            throw new Exception('Wallet funding amount must be greater than zero');
        }

        $providerReferenceSafe = mysqli_real_escape_string($conn, $providerReference);
        $existingSql = "SELECT * FROM wallet_funding_transactions WHERE provider_reference = '$providerReferenceSafe' LIMIT 1";
        $existingRs = mysqli_query($conn, $existingSql);
        if ($existingRs && mysqli_num_rows($existingRs) > 0) {
            $existingRow = mysqli_fetch_assoc($existingRs);
            return [
                'status' => 'exists',
                'amount' => (int)($existingRow['amount'] ?? 0),
                'funding' => $existingRow,
            ];
        }

        $providerEventSafe = mysqli_real_escape_string($conn, $providerEvent);
        $providerTransactionIdSafe = mysqli_real_escape_string($conn, $providerTransactionId);
        $providerAccountIdSafe = mysqli_real_escape_string($conn, $providerAccountId);
        $accountNumberSafe = mysqli_real_escape_string($conn, $accountNumber);
        $descriptionSafe = mysqli_real_escape_string($conn, $description);
        $sourceSafe = mysqli_real_escape_string($conn, $source);
        $rawPayloadSafe = mysqli_real_escape_string($conn, (string)$rawPayload);

        mysqli_begin_transaction($conn);
        try {
            $walletLockSql = "SELECT balance FROM user_wallets WHERE id = $walletId LIMIT 1 FOR UPDATE";
            $walletLockRs = mysqli_query($conn, $walletLockSql);
            if (!$walletLockRs || mysqli_num_rows($walletLockRs) < 1) {
                throw new Exception('Unable to lock wallet balance for funding');
            }
            $walletRow = mysqli_fetch_assoc($walletLockRs);
            $balanceBefore = (int)($walletRow['balance'] ?? 0);
            $balanceAfter = $balanceBefore + $amount;

            $insertFundingSql = "INSERT INTO wallet_funding_transactions (
                    wallet_id, user_id, provider, provider_reference, provider_event,
                    provider_transaction_id, provider_account_id, account_number, amount,
                    status, source, description, raw_payload, posted_at
                ) VALUES (
                    $walletId, $userId, 'paystack', '$providerReferenceSafe', '$providerEventSafe',
                    '$providerTransactionIdSafe', '$providerAccountIdSafe', '$accountNumberSafe', $amount,
                    'posted', '$sourceSafe', '$descriptionSafe', '$rawPayloadSafe', NOW()
                )";
            if (!mysqli_query($conn, $insertFundingSql)) {
                throw new Exception('Failed to insert wallet funding transaction: ' . mysqli_error($conn));
            }

            $ledgerReference = mysqli_real_escape_string($conn, 'wallet_funding:' . $providerReference);
            $insertLedgerSql = "INSERT INTO wallet_ledger_entries (
                    wallet_id, entry_type, amount, balance_before, balance_after, status,
                    reference, provider_reference, description, metadata
                ) VALUES (
                    $walletId, 'credit', $amount, $balanceBefore, $balanceAfter, 'posted',
                    '$ledgerReference', '$providerReferenceSafe', '$descriptionSafe', '$rawPayloadSafe'
                )";
            if (!mysqli_query($conn, $insertLedgerSql)) {
                throw new Exception('Failed to insert wallet ledger entry: ' . mysqli_error($conn));
            }

            $updateWalletSql = "UPDATE user_wallets SET balance = $balanceAfter, updated_at = NOW() WHERE id = $walletId";
            if (!mysqli_query($conn, $updateWalletSql)) {
                throw new Exception('Failed to update wallet balance: ' . mysqli_error($conn));
            }

            $existingTxSql = "SELECT id FROM transactions WHERE ref_id = '$providerReferenceSafe' LIMIT 1 FOR UPDATE";
            $existingTxRs = mysqli_query($conn, $existingTxSql);
            if (!$existingTxRs) {
                throw new Exception('Failed to inspect existing transaction row: ' . mysqli_error($conn));
            }
            if (mysqli_num_rows($existingTxRs) > 0) {
                $updateTxSql = "UPDATE transactions
                                SET user_id = $userId,
                                    amount = $amount,
                                    charge = 0,
                                    profit = 0,
                                    refund = 0,
                                    status = 'successful',
                                    medium = 'PAYSTACK',
                                    payment_channel = 'wallet',
                                    transaction_context = 'wallet_funding'
                                WHERE ref_id = '$providerReferenceSafe'";
                if (!mysqli_query($conn, $updateTxSql)) {
                    throw new Exception('Failed to update funding transaction record: ' . mysqli_error($conn));
                }
            } else {
                $insertTxSql = "INSERT INTO transactions (
                        ref_id, user_id, amount, charge, profit, refund, status, medium, payment_channel, transaction_context
                    ) VALUES (
                        '$providerReferenceSafe', $userId, $amount, 0, 0, 0, 'successful', 'PAYSTACK', 'wallet', 'wallet_funding'
                    )";
                if (!mysqli_query($conn, $insertTxSql)) {
                    throw new Exception('Failed to create funding transaction record: ' . mysqli_error($conn));
                }
            }

            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }

        nivasityWalletLog('Applied wallet funding transaction', [
            'wallet_id' => $walletId,
            'user_id' => $userId,
            'provider_reference' => $providerReference,
            'amount' => $amount,
            'source' => $source,
        ]);

        return [
            'status' => 'posted',
            'amount' => $amount,
        ];
    }
}

if (!function_exists('nivasitySyncWalletFundingFromPaystack')) {
    function nivasitySyncWalletFundingFromPaystack($conn, $userId, $source = 'refresh') {
        $wallet = nivasityGetUserWallet($conn, $userId);
        if (!$wallet || empty($wallet['provider_account_id'])) {
            return [
                'status' => 'no_wallet',
                'processed' => 0,
                'posted' => 0,
            ];
        }

        if (!defined('PAYSTACK_SECRET_KEY') || PAYSTACK_SECRET_KEY === '') {
            throw new Exception('Paystack secret key is not configured');
        }

        $providerAccountId = rawurlencode((string)$wallet['provider_account_id']);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.paystack.co/dedicated_account/transactions?dedicated_account_id=' . $providerAccountId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            ],
        ]);
        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            throw new Exception('Failed to sync wallet funding transactions: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if (!isset($decoded['status']) || $decoded['status'] !== true || !isset($decoded['data']) || !is_array($decoded['data'])) {
            throw new Exception('Unexpected Paystack wallet funding sync response');
        }

        $processed = 0;
        $posted = 0;
        foreach ($decoded['data'] as $row) {
            $processed++;
            try {
                $applyResult = nivasityApplyWalletFundingTransaction($conn, $wallet, $row, $source, 'dedicated_account.credit');
                if (($applyResult['status'] ?? '') === 'posted') {
                    $posted++;
                }
            } catch (Throwable $e) {
                nivasityWalletLog('Wallet funding sync row failed', [
                    'user_id' => (int)$userId,
                    'error' => $e->getMessage(),
                    'reference' => $row['reference'] ?? null,
                ]);
            }
        }

        return [
            'status' => 'ok',
            'processed' => $processed,
            'posted' => $posted,
        ];
    }
}

if (!function_exists('nivasityDeterminePaystackWebhookIntent')) {
    function nivasityDeterminePaystackWebhookIntent($conn, $payload) {
        $data = $payload['data'] ?? [];
        $reference = trim((string)($data['reference'] ?? ''));

        if ($reference !== '') {
            $referenceSafe = mysqli_real_escape_string($conn, $reference);
            $cartSql = "SELECT 1 FROM cart WHERE ref_id = '$referenceSafe' LIMIT 1";
            $cartRs = mysqli_query($conn, $cartSql);
            if ($cartRs && mysqli_num_rows($cartRs) > 0) {
                return 'purchase';
            }
        }

        $wallet = nivasityResolveWalletFromPaystackPayload($conn, $data);
        if ($wallet) {
            return 'wallet_funding';
        }

        return 'unknown';
    }
}
