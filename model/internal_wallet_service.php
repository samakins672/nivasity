<?php

require_once __DIR__ . '/../config/fw.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/mail.php';

if (!function_exists('nivasityWalletLog')) {
    function nivasityWalletLog($message, $context = []) {
        $payload = '[NIVASITY_WALLET] ' . $message;
        if (!empty($context)) {
            $payload .= ' ' . json_encode($context);
        }
        error_log($payload);
    }
}

if (!function_exists('nivasityFormatWalletAmount')) {
    function nivasityFormatWalletAmount($amount) {
        return 'NGN ' . number_format((int)round((float)$amount), 2);
    }
}

if (!function_exists('nivasityGetWalletUserProfile')) {
    function nivasityGetWalletUserProfile($conn, $userId) {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return null;
        }

        $sql = "SELECT id, first_name, last_name, email FROM users WHERE id = $userId LIMIT 1";
        $rs = mysqli_query($conn, $sql);
        if ($rs && mysqli_num_rows($rs) > 0) {
            return mysqli_fetch_assoc($rs);
        }

        return null;
    }
}

if (!function_exists('nivasityUsersHasWalletPinHashColumn')) {
    function nivasityUsersHasWalletPinHashColumn($conn) {
        static $hasColumn = null;

        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $rs = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'wallet_pin_hash'");
        $hasColumn = $rs && mysqli_num_rows($rs) > 0;
        return $hasColumn;
    }
}

if (!function_exists('nivasityUsersHasWalletPinUpdatedAtColumn')) {
    function nivasityUsersHasWalletPinUpdatedAtColumn($conn) {
        static $hasColumn = null;

        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $rs = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'wallet_pin_updated_at'");
        $hasColumn = $rs && mysqli_num_rows($rs) > 0;
        return $hasColumn;
    }
}

if (!function_exists('nivasityWalletPinTokensTableExists')) {
    function nivasityWalletPinTokensTableExists($conn) {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        $rs = mysqli_query($conn, "SHOW TABLES LIKE 'wallet_pin_tokens'");
        $exists = $rs && mysqli_num_rows($rs) > 0;
        return $exists;
    }
}

if (!function_exists('nivasityWalletPinTokensHasColumn')) {
    function nivasityWalletPinTokensHasColumn($conn, $columnName) {
        static $columns = [];

        if (array_key_exists($columnName, $columns)) {
            return $columns[$columnName];
        }

        $columnNameSafe = mysqli_real_escape_string($conn, (string)$columnName);
        $rs = mysqli_query($conn, "SHOW COLUMNS FROM wallet_pin_tokens LIKE '$columnNameSafe'");
        $columns[$columnName] = $rs && mysqli_num_rows($rs) > 0;
        return $columns[$columnName];
    }
}

if (!function_exists('nivasityWalletFundingTransactionsHasColumn')) {
    function nivasityWalletFundingTransactionsHasColumn($conn, $columnName) {
        static $columns = [];

        if (array_key_exists($columnName, $columns)) {
            return $columns[$columnName];
        }

        $columnNameSafe = mysqli_real_escape_string($conn, (string)$columnName);
        $rs = mysqli_query($conn, "SHOW COLUMNS FROM wallet_funding_transactions LIKE '$columnNameSafe'");
        $columns[$columnName] = $rs && mysqli_num_rows($rs) > 0;
        return $columns[$columnName];
    }
}

if (!function_exists('nivasityRequireWalletPinInfrastructure')) {
    function nivasityRequireWalletPinInfrastructure($conn) {
        if (
            !nivasityUsersHasWalletPinHashColumn($conn)
            || !nivasityUsersHasWalletPinUpdatedAtColumn($conn)
            || !nivasityWalletPinTokensTableExists($conn)
            || !nivasityWalletPinTokensHasColumn($conn, 'verified_at')
            || !nivasityWalletPinTokensHasColumn($conn, 'verification_token_hash')
            || !nivasityWalletPinTokensHasColumn($conn, 'verification_token_expires_at')
        ) {
            throw new Exception('Wallet PIN management is not available until the latest wallet PIN SQL update is applied.');
        }
    }
}

if (!function_exists('nivasityIsValidWalletPin')) {
    function nivasityIsValidWalletPin($pin) {
        return preg_match('/^\d{4}$/', (string)$pin) === 1;
    }
}

if (!function_exists('nivasityUserHasWalletPin')) {
    function nivasityUserHasWalletPin($conn, $userId) {
        $userId = (int)$userId;
        if ($userId <= 0 || !nivasityUsersHasWalletPinHashColumn($conn)) {
            return false;
        }

        $sql = "SELECT wallet_pin_hash FROM users WHERE id = $userId LIMIT 1";
        $rs = mysqli_query($conn, $sql);
        if (!$rs || mysqli_num_rows($rs) < 1) {
            return false;
        }

        $row = mysqli_fetch_assoc($rs);
        return trim((string)($row['wallet_pin_hash'] ?? '')) !== '';
    }
}

if (!function_exists('nivasitySendWalletPinCode')) {
    function nivasitySendWalletPinCode($conn, $userId) {
        $userId = (int)$userId;
        if ($userId <= 0) {
            throw new Exception('Authentication required');
        }

        nivasityRequireWalletPinInfrastructure($conn);

        $wallet = nivasityGetUserWallet($conn, $userId);
        if (!$wallet || (int)($wallet['id'] ?? 0) <= 0) {
            throw new Exception('Create your wallet before setting a Wallet PIN');
        }

        $user = nivasityGetWalletUserProfile($conn, $userId);
        if (!$user || empty($user['email'])) {
            throw new Exception('User email address was not found');
        }

        $purpose = nivasityUserHasWalletPin($conn, $userId) ? 'update' : 'create';
        $code = (string)rand(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $codeSafe = mysqli_real_escape_string($conn, $code);
        $purposeSafe = mysqli_real_escape_string($conn, $purpose);
        $expiresAtSafe = mysqli_real_escape_string($conn, $expiresAt);

        mysqli_query($conn, "DELETE FROM wallet_pin_tokens WHERE user_id = $userId");
        $insertSql = "INSERT INTO wallet_pin_tokens (user_id, code, purpose, expires_at, created_at) VALUES ($userId, '$codeSafe', '$purposeSafe', '$expiresAtSafe', NOW())";
        if (!mysqli_query($conn, $insertSql)) {
            throw new Exception('Failed to create Wallet PIN verification code: ' . mysqli_error($conn));
        }

        $displayName = trim((string)(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
        if ($displayName === '') {
            $displayName = 'there';
        }
        $subject = $purpose === 'create' ? 'Create your Nivasity Wallet PIN' : 'Update your Nivasity Wallet PIN';
        $body = '<p>Hello ' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Your Wallet PIN verification code is <b>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</b>.</p>'
            . '<p>This code expires in 10 minutes. Use it to ' . ($purpose === 'create' ? 'create' : 'update') . ' your 4-digit Wallet PIN.</p>'
            . '<p>If you did not request this, please ignore this email.</p><p>Regards,<br><b>Nivasity Team</b></p>';

        $mailStatus = function_exists('sendBrevoMail') ? sendBrevoMail($subject, $body, (string)$user['email']) : sendMail($subject, $body, (string)$user['email']);
        if ($mailStatus !== 'success' && function_exists('sendMail')) {
            $mailStatus = sendMail($subject, $body, (string)$user['email']);
        }
        if ($mailStatus !== 'success') {
            throw new Exception('Failed to send Wallet PIN code to your email');
        }

        return [
            'status' => 'sent',
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
        ];
    }
}

if (!function_exists('nivasitySaveWalletPin')) {
    function nivasityVerifyWalletPinCode($conn, $userId, $code) {
        $userId = (int)$userId;
        $code = trim((string)$code);

        if ($userId <= 0) {
            throw new Exception('Authentication required');
        }

        nivasityRequireWalletPinInfrastructure($conn);

        if (!preg_match('/^\d{6}$/', $code)) {
            throw new Exception('Enter the 6-digit code sent to your email');
        }

        $wallet = nivasityGetUserWallet($conn, $userId);
        if (!$wallet || (int)($wallet['id'] ?? 0) <= 0) {
            throw new Exception('Create your wallet before setting a Wallet PIN');
        }

        $codeSafe = mysqli_real_escape_string($conn, $code);
        $nowSafe = mysqli_real_escape_string($conn, date('Y-m-d H:i:s'));
        $tokenSql = "SELECT * FROM wallet_pin_tokens WHERE user_id = $userId AND code = '$codeSafe' AND consumed_at IS NULL AND expires_at >= '$nowSafe' ORDER BY id DESC LIMIT 1";
        $tokenRs = mysqli_query($conn, $tokenSql);
        if (!$tokenRs || mysqli_num_rows($tokenRs) < 1) {
            throw new Exception('Invalid or expired Wallet PIN code');
        }

        $tokenRow = mysqli_fetch_assoc($tokenRs);
        $verificationToken = bin2hex(random_bytes(24));
        $verificationTokenHash = mysqli_real_escape_string($conn, password_hash($verificationToken, PASSWORD_DEFAULT));
        $verificationTokenExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $verificationTokenExpiresAtSafe = mysqli_real_escape_string($conn, $verificationTokenExpiresAt);
        $tokenId = (int)($tokenRow['id'] ?? 0);

        $updateSql = "UPDATE wallet_pin_tokens SET verified_at = NOW(), verification_token_hash = '$verificationTokenHash', verification_token_expires_at = '$verificationTokenExpiresAtSafe' WHERE id = $tokenId LIMIT 1";
        if (!mysqli_query($conn, $updateSql)) {
            throw new Exception('Failed to verify Wallet PIN code: ' . mysqli_error($conn));
        }

        return [
            'status' => 'verified',
            'purpose' => (string)($tokenRow['purpose'] ?? (nivasityUserHasWalletPin($conn, $userId) ? 'update' : 'create')),
            'pin_token' => $verificationToken,
            'token_expires_at' => $verificationTokenExpiresAt,
        ];
    }
}

if (!function_exists('nivasitySaveWalletPin')) {
    function nivasitySaveWalletPin($conn, $userId, $pinToken, $pin, $confirmPin) {
        $userId = (int)$userId;
        $pinToken = trim((string)$pinToken);
        $pin = trim((string)$pin);
        $confirmPin = trim((string)$confirmPin);

        if ($userId <= 0) {
            throw new Exception('Authentication required');
        }

        nivasityRequireWalletPinInfrastructure($conn);

        if (!nivasityIsValidWalletPin($pin)) {
            throw new Exception('Wallet PIN must be exactly 4 digits');
        }
        if ($pin !== $confirmPin) {
            throw new Exception('Wallet PIN confirmation does not match');
        }
        if ($pinToken === '') {
            throw new Exception('Wallet PIN verification token is required');
        }

        $wallet = nivasityGetUserWallet($conn, $userId);
        if (!$wallet || (int)($wallet['id'] ?? 0) <= 0) {
            throw new Exception('Create your wallet before setting a Wallet PIN');
        }

        $nowSafe = mysqli_real_escape_string($conn, date('Y-m-d H:i:s'));
        $tokenSql = "SELECT * FROM wallet_pin_tokens WHERE user_id = $userId AND consumed_at IS NULL AND verified_at IS NOT NULL AND verification_token_expires_at >= '$nowSafe' ORDER BY id DESC LIMIT 5";
        $tokenRs = mysqli_query($conn, $tokenSql);
        if (!$tokenRs || mysqli_num_rows($tokenRs) < 1) {
            throw new Exception('Invalid or expired Wallet PIN verification session');
        }

        $verifiedTokenRow = null;
        while ($row = mysqli_fetch_assoc($tokenRs)) {
            $storedHash = (string)($row['verification_token_hash'] ?? '');
            if ($storedHash !== '' && password_verify($pinToken, $storedHash)) {
                $verifiedTokenRow = $row;
                break;
            }
        }

        if (!$verifiedTokenRow) {
            throw new Exception('Invalid or expired Wallet PIN verification session');
        }

        $pinHashSafe = mysqli_real_escape_string($conn, password_hash($pin, PASSWORD_DEFAULT));
        $verifiedTokenId = (int)($verifiedTokenRow['id'] ?? 0);
        mysqli_begin_transaction($conn);
        try {
            $updates = ["wallet_pin_hash = '$pinHashSafe'"];
            if (nivasityUsersHasWalletPinUpdatedAtColumn($conn)) {
                $updates[] = 'wallet_pin_updated_at = NOW()';
            }
            $updateSql = 'UPDATE users SET ' . implode(', ', $updates) . " WHERE id = $userId LIMIT 1";
            if (!mysqli_query($conn, $updateSql)) {
                throw new Exception('Failed to save Wallet PIN: ' . mysqli_error($conn));
            }

            $consumeSql = "UPDATE wallet_pin_tokens SET consumed_at = NOW(), verification_token_hash = NULL, verification_token_expires_at = NULL WHERE id = $verifiedTokenId LIMIT 1";
            if (!mysqli_query($conn, $consumeSql)) {
                throw new Exception('Failed to consume Wallet PIN verification session: ' . mysqli_error($conn));
            }

            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }

        return [
            'status' => 'saved',
            'has_pin' => true,
        ];
    }
}

if (!function_exists('nivasityVerifyWalletPin')) {
    function nivasityVerifyWalletPin($conn, $userId, $pin) {
        $userId = (int)$userId;
        $pin = trim((string)$pin);
        if ($userId <= 0) {
            throw new Exception('Authentication required');
        }

        nivasityRequireWalletPinInfrastructure($conn);

        if (!nivasityIsValidWalletPin($pin)) {
            throw new Exception('Enter your 4-digit Wallet PIN to continue');
        }

        $sql = "SELECT wallet_pin_hash FROM users WHERE id = $userId LIMIT 1";
        $rs = mysqli_query($conn, $sql);
        if (!$rs || mysqli_num_rows($rs) < 1) {
            throw new Exception('Unable to verify Wallet PIN for this user');
        }

        $row = mysqli_fetch_assoc($rs);
        $walletPinHash = (string)($row['wallet_pin_hash'] ?? '');
        if ($walletPinHash === '') {
            throw new Exception('Set up your Wallet PIN before paying with wallet');
        }
        if (!password_verify($pin, $walletPinHash)) {
            throw new Exception('Incorrect Wallet PIN');
        }

        return true;
    }
}

if (!function_exists('nivasitySendWalletAlert')) {
    function nivasitySendWalletAlert($conn, $userId, $eventType, $payload = []) {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return;
        }

        $user = nivasityGetWalletUserProfile($conn, $userId);
        if (!$user) {
            return;
        }

        $amountText = nivasityFormatWalletAmount($payload['amount'] ?? 0);
        $balanceText = nivasityFormatWalletAmount($payload['balance_after'] ?? 0);
        $reference = trim((string)($payload['reference'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $displayName = trim((string)(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
        if ($displayName === '') {
            $displayName = 'there';
        }

        $title = 'Wallet Update';
        $body = 'Your wallet activity has been updated.';
        $emailSubject = 'Nivasity Wallet Update';
        $emailBody = '<p>Hello ' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . ',</p>';

        if ($eventType === 'credit') {
            $title = 'Wallet Credited';
            $body = 'Your Nivasity Wallet has been credited with ' . $amountText . '. New balance: ' . $balanceText . '.';
            $emailSubject = 'Nivasity Wallet Credited';
            $emailBody .= '<p>Your Nivasity Wallet has been credited with <b>' . htmlspecialchars($amountText, ENT_QUOTES, 'UTF-8') . '</b>.</p>'
                . '<p>New balance: <b>' . htmlspecialchars($balanceText, ENT_QUOTES, 'UTF-8') . '</b>.</p>';
        } elseif ($eventType === 'refund') {
            $title = 'Wallet Refund Credited';
            $body = 'A refund of ' . $amountText . ' has been credited to your Nivasity Wallet. New balance: ' . $balanceText . '.';
            $emailSubject = 'Nivasity Wallet Refund Credited';
            $emailBody .= '<p>A refund of <b>' . htmlspecialchars($amountText, ENT_QUOTES, 'UTF-8') . '</b> has been credited to your Nivasity Wallet.</p>'
                . '<p>New balance: <b>' . htmlspecialchars($balanceText, ENT_QUOTES, 'UTF-8') . '</b>.</p>';
        } elseif ($eventType === 'debit') {
            $title = 'Wallet Debited';
            $body = 'Your Nivasity Wallet was debited ' . $amountText . '. New balance: ' . $balanceText . '.';
            $emailSubject = 'Nivasity Wallet Debited';
            $emailBody .= '<p>Your Nivasity Wallet was debited <b>' . htmlspecialchars($amountText, ENT_QUOTES, 'UTF-8') . '</b>.</p>'
                . '<p>New balance: <b>' . htmlspecialchars($balanceText, ENT_QUOTES, 'UTF-8') . '</b>.</p>';
        }

        if ($description !== '') {
            $body .= ' ' . $description;
            $emailBody .= '<p>Description: ' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        if ($reference !== '') {
            $emailBody .= '<p>Reference: <b>' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</b></p>';
        }
        $emailBody .= '<p>If you did not expect this wallet activity, contact support immediately.</p><p>Regards,<br><b>Nivasity Team</b></p>';

        try {
            notifyUser($conn, $userId, $title, $body, 'wallet', [
                'action' => 'wallet_activity',
                'wallet_event' => $eventType,
                'amount' => (int)($payload['amount'] ?? 0),
                'balance_after' => (int)($payload['balance_after'] ?? 0),
                'reference' => $reference,
                'payment_channel' => 'wallet',
            ]);
        } catch (Throwable $e) {
            nivasityWalletLog('Wallet push notification failed', [
                'user_id' => $userId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }

        $email = trim((string)($user['email'] ?? ''));
        if ($email !== '') {
            try {
                $mailStatus = function_exists('sendBrevoMail') ? sendBrevoMail($emailSubject, $emailBody, $email) : sendMail($emailSubject, $emailBody, $email);
                if ($mailStatus !== 'success' && function_exists('sendMail')) {
                    sendMail($emailSubject, $emailBody, $email);
                }
            } catch (Throwable $e) {
                nivasityWalletLog('Wallet email alert failed', [
                    'user_id' => $userId,
                    'event_type' => $eventType,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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

if (!function_exists('nivasityGetWalletDashboardPayload')) {
    function nivasityGetWalletDashboardPayload($conn, $userId, $limit = 25) {
        $userId = (int)$userId;
        $limit = (int)$limit;
        if ($limit <= 0) {
            $limit = 25;
        }

        $wallet = nivasityGetUserWallet($conn, $userId);
        $hasWalletPin = $wallet ? nivasityUserHasWalletPin($conn, $userId) : false;
        $entries = [];
        $creditsTotal = 0;
        $debitsTotal = 0;

        if ($wallet && (int)($wallet['id'] ?? 0) > 0) {
            $walletId = (int)$wallet['id'];
            $entriesQuery = mysqli_query($conn, "SELECT * FROM wallet_ledger_entries WHERE wallet_id = $walletId ORDER BY created_at DESC, id DESC LIMIT $limit");
            if ($entriesQuery) {
                while ($entry = mysqli_fetch_assoc($entriesQuery)) {
                    $entryType = strtolower((string)($entry['entry_type'] ?? 'adjustment'));
                    $amount = (int)($entry['amount'] ?? 0);
                    $isCreditLike = in_array($entryType, ['credit', 'refund'], true);
                    if ($isCreditLike) {
                        $creditsTotal += $amount;
                    } elseif (in_array($entryType, ['debit', 'fee'], true)) {
                        $debitsTotal += $amount;
                    }

                    $entry['display_reference'] = (string)($entry['provider_reference'] ?: $entry['reference']);
                    $entry['display_date'] = !empty($entry['created_at']) ? date('j M, Y h:i a', strtotime((string)$entry['created_at'])) : '';
                    $entry['badge_class'] = $isCreditLike ? 'bg-success' : (in_array($entryType, ['debit', 'fee'], true) ? 'bg-danger' : 'bg-secondary');
                    $entry['amount_class'] = $isCreditLike ? 'text-success' : 'text-danger';
                    $entry['amount_sign'] = $isCreditLike ? '+' : '-';
                    $entries[] = $entry;
                }
            }
        }

        return [
            'wallet' => $wallet,
            'has_pin' => $hasWalletPin,
            'credits_total' => $creditsTotal,
            'debits_total' => $debitsTotal,
            'entries_count' => count($entries),
            'entries' => $entries,
        ];
    }
}

if (!function_exists('nivasityGetWalletTransactionsPayload')) {
    function nivasityGetWalletTransactionsPayload($conn, $userId, $page = 1, $limit = 20) {
        $userId = (int)$userId;
        $page = max(1, (int)$page);
        $limit = (int)$limit;
        if ($limit <= 0) {
            $limit = 20;
        }
        if ($limit > 20) {
            $limit = 20;
        }

        $wallet = nivasityGetUserWallet($conn, $userId);
        $hasWalletPin = $wallet ? nivasityUserHasWalletPin($conn, $userId) : false;
        $transactions = [];
        $pagination = [
            'total' => 0,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => 0,
        ];

        if (!$wallet || (int)($wallet['id'] ?? 0) <= 0) {
            return [
                'wallet' => null,
                'has_wallet' => false,
                'has_pin' => $hasWalletPin,
                'transactions' => $transactions,
                'pagination' => $pagination,
            ];
        }

        $walletId = (int)$wallet['id'];
        $offset = ($page - 1) * $limit;

        $countQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM wallet_ledger_entries WHERE wallet_id = $walletId");
        if ($countQuery) {
            $countRow = mysqli_fetch_assoc($countQuery);
            $pagination['total'] = (int)($countRow['total'] ?? 0);
            $pagination['total_pages'] = $pagination['total'] > 0 ? (int)ceil($pagination['total'] / $limit) : 0;
        }

        $entriesQuery = mysqli_query($conn, "SELECT * FROM wallet_ledger_entries WHERE wallet_id = $walletId ORDER BY created_at DESC, id DESC LIMIT $limit OFFSET $offset");
        if ($entriesQuery) {
            while ($entry = mysqli_fetch_assoc($entriesQuery)) {
                $entryType = strtolower((string)($entry['entry_type'] ?? 'adjustment'));
                $amount = (int)($entry['amount'] ?? 0);
                $isCredit = in_array($entryType, ['credit', 'refund'], true);
                $isDebit = in_array($entryType, ['debit', 'fee'], true);
                $direction = $isCredit ? 'credit' : ($isDebit ? 'debit' : 'neutral');

                $transactions[] = [
                    'id' => (int)($entry['id'] ?? 0),
                    'entry_type' => $entryType,
                    'direction' => $direction,
                    'amount' => $amount,
                    'signed_amount' => $isCredit ? $amount : ($isDebit ? ($amount * -1) : $amount),
                    'status' => (string)($entry['status'] ?? ''),
                    'reference' => (string)($entry['reference'] ?? ''),
                    'provider_reference' => !empty($entry['provider_reference']) ? (string)$entry['provider_reference'] : null,
                    'display_reference' => (string)($entry['provider_reference'] ?: $entry['reference']),
                    'description' => (string)($entry['description'] ?? ''),
                    'balance_before' => (int)($entry['balance_before'] ?? 0),
                    'balance_after' => (int)($entry['balance_after'] ?? 0),
                    'created_at' => (string)($entry['created_at'] ?? ''),
                    'display_date' => !empty($entry['created_at']) ? date('j M, Y h:i a', strtotime((string)$entry['created_at'])) : '',
                ];
            }
        }

        return [
            'wallet' => $wallet,
            'has_wallet' => true,
            'has_pin' => $hasWalletPin,
            'transactions' => $transactions,
            'pagination' => $pagination,
        ];
    }
}

if (!function_exists('nivasityWalletFeeThresholdsTableExists')) {
    function nivasityWalletFeeThresholdsTableExists($conn) {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        $rs = mysqli_query($conn, "SHOW TABLES LIKE 'wallet_fee_thresholds'");
        $exists = $rs && mysqli_num_rows($rs) > 0;
        return $exists;
    }
}

if (!function_exists('nivasityGetWalletHandlingFeeBreakdown')) {
    function nivasityGetWalletHandlingFeeBreakdown($conn, $subtotal, $gatewayCharge = null) {
        $subtotal = (int)round((float)$subtotal);
        $gatewayCharge = $gatewayCharge === null ? null : (int)round((float)$gatewayCharge);

        if ($subtotal <= 0) {
            return [
                'subtotal' => 0,
                'charge' => 0,
                'configured_charge' => 0,
                'gateway_charge' => max(0, (int)$gatewayCharge),
                'total_amount' => 0,
            ];
        }

        $configuredCharge = 0;
        if (nivasityWalletFeeThresholdsTableExists($conn)) {
            $subtotalValue = (float)$subtotal;
            $thresholdSql = "SELECT fee_amount
                             FROM wallet_fee_thresholds
                             WHERE status = 'active'
                               AND min_subtotal <= $subtotalValue
                               AND (max_subtotal IS NULL OR max_subtotal = 0 OR max_subtotal >= $subtotalValue)
                             ORDER BY min_subtotal DESC, id DESC
                             LIMIT 1";
            $thresholdRs = mysqli_query($conn, $thresholdSql);
            if ($thresholdRs && mysqli_num_rows($thresholdRs) > 0) {
                $thresholdRow = mysqli_fetch_assoc($thresholdRs);
                $configuredCharge = max(0, (int)round((float)($thresholdRow['fee_amount'] ?? 0)));
            }
        }

        $effectiveCharge = $configuredCharge;
        if ($gatewayCharge !== null) {
            $gatewayCharge = max(0, $gatewayCharge);
            if ($gatewayCharge <= 0) {
                $effectiveCharge = 0;
            } else {
                $effectiveCharge = min($configuredCharge, max(0, $gatewayCharge - 1));
            }
        }

        return [
            'subtotal' => $subtotal,
            'charge' => $effectiveCharge,
            'configured_charge' => $configuredCharge,
            'gateway_charge' => $gatewayCharge,
            'total_amount' => $subtotal + $effectiveCharge,
        ];
    }
}

if (!function_exists('nivasityPaystackRequest')) {
    function nivasityPaystackRequest($method, $path, $payload = null) {
        if (!defined('PAYSTACK_SECRET_KEY') || PAYSTACK_SECRET_KEY === '') {
            throw new Exception('Paystack secret key is not configured');
        }

        $path = '/' . ltrim((string)$path, '/');
        $url = 'https://api.paystack.co' . $path;
        $encodedPayload = $payload === null ? null : json_encode($payload);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper((string)$method),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            ],
        ]);

        if ($encodedPayload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedPayload);
        }

        $rawBody = curl_exec($curl);
        $curlError = curl_error($curl);
        $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return [
            'ok' => $curlError === '' && $statusCode >= 200 && $statusCode < 300,
            'status_code' => $statusCode,
            'error' => $curlError,
            'raw_body' => $rawBody,
            'data' => is_string($rawBody) ? json_decode($rawBody, true) : null,
        ];
    }
}

if (!function_exists('nivasityIsPaystackTestMode')) {
    function nivasityIsPaystackTestMode() {
        if (!defined('PAYSTACK_SECRET_KEY')) {
            return false;
        }

        return stripos((string)PAYSTACK_SECRET_KEY, 'sk_test_') === 0;
    }
}

if (!function_exists('nivasityGetPaystackDedicatedAccountPreferredBank')) {
    function nivasityGetPaystackDedicatedAccountPreferredBank() {
        return nivasityIsPaystackTestMode() ? 'test-bank' : 'wema-bank';
    }
}

if (!function_exists('nivasityExtractPaystackErrorMessage')) {
    function nivasityExtractPaystackErrorMessage($response, $fallback = 'Paystack request failed') {
        $fallback = trim((string)$fallback);
        $decoded = isset($response['data']) && is_array($response['data']) ? $response['data'] : null;

        if ($decoded && !empty($decoded['message'])) {
            return (string)$decoded['message'];
        }

        if (!empty($response['error'])) {
            return (string)$response['error'];
        }

        if (!empty($response['raw_body'])) {
            return (string)$response['raw_body'];
        }

        return $fallback !== '' ? $fallback : 'Paystack request failed';
    }
}

if (!function_exists('nivasityUsersHasPaystackCustomerCodeColumn')) {
    function nivasityUsersHasPaystackCustomerCodeColumn($conn) {
        static $hasColumn = null;

        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $rs = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'paystack_customer_code'");
        $hasColumn = $rs && mysqli_num_rows($rs) > 0;
        return $hasColumn;
    }
}

if (!function_exists('nivasityUsersHasPaystackCustomerIdColumn')) {
    function nivasityUsersHasPaystackCustomerIdColumn($conn) {
        static $hasColumn = null;

        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $rs = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'paystack_customer_id'");
        $hasColumn = $rs && mysqli_num_rows($rs) > 0;
        return $hasColumn;
    }
}

if (!function_exists('nivasityPersistPaystackCustomerCode')) {
    function nivasityPersistPaystackCustomerCode($conn, $userId, $customerCode) {
        $userId = (int)$userId;
        $customerCode = trim((string)$customerCode);
        if ($userId <= 0 || $customerCode === '' || !nivasityUsersHasPaystackCustomerCodeColumn($conn)) {
            return false;
        }

        $customerCodeSafe = mysqli_real_escape_string($conn, $customerCode);
        $sql = "UPDATE users SET paystack_customer_code = '$customerCodeSafe' WHERE id = $userId LIMIT 1";
        return mysqli_query($conn, $sql) !== false;
    }
}

if (!function_exists('nivasityPersistPaystackCustomerDetails')) {
    function nivasityPersistPaystackCustomerDetails($conn, $userId, $customer) {
        $userId = (int)$userId;
        if ($userId <= 0 || !is_array($customer)) {
            return false;
        }

        $updates = [];

        $customerCode = trim((string)($customer['customer_code'] ?? ''));
        if ($customerCode !== '' && nivasityUsersHasPaystackCustomerCodeColumn($conn)) {
            $customerCodeSafe = mysqli_real_escape_string($conn, $customerCode);
            $updates[] = "paystack_customer_code = '$customerCodeSafe'";
        }

        $customerId = (int)($customer['id'] ?? 0);
        if ($customerId > 0 && nivasityUsersHasPaystackCustomerIdColumn($conn)) {
            $updates[] = "paystack_customer_id = $customerId";
        }

        if (empty($updates)) {
            return false;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $updates) . " WHERE id = $userId LIMIT 1";
        return mysqli_query($conn, $sql) !== false;
    }
}

if (!function_exists('nivasityFetchPaystackCustomer')) {
    function nivasityFetchPaystackCustomer($emailOrCode) {
        $emailOrCode = trim((string)$emailOrCode);
        if ($emailOrCode === '') {
            return null;
        }

        $response = nivasityPaystackRequest('GET', '/customer/' . rawurlencode($emailOrCode));
        if ((int)($response['status_code'] ?? 0) === 404) {
            return null;
        }
        if (!$response['ok']) {
            $message = $response['error'] !== '' ? $response['error'] : (string)($response['raw_body'] ?? 'Unable to fetch Paystack customer');
            throw new Exception('Failed to fetch Paystack customer: ' . $message);
        }

        $decoded = $response['data'];
        if (!is_array($decoded) || !isset($decoded['status']) || $decoded['status'] !== true || empty($decoded['data'])) {
            return null;
        }

        return $decoded['data'];
    }
}

if (!function_exists('nivasityFetchPaystackCustomerByEmail')) {
    function nivasityFetchPaystackCustomerByEmail($email) {
        return nivasityFetchPaystackCustomer($email);
    }
}

if (!function_exists('nivasityCreatePaystackCustomer')) {
    function nivasityCreatePaystackCustomer($user) {
        $payload = [
            'email' => (string)($user['email'] ?? ''),
            'first_name' => (string)($user['first_name'] ?? ''),
            'last_name' => (string)($user['last_name'] ?? ''),
            'phone' => (string)($user['phone'] ?? ''),
        ];

        $response = nivasityPaystackRequest('POST', '/customer', $payload);
        if (!$response['ok']) {
            $message = $response['error'] !== '' ? $response['error'] : (string)($response['raw_body'] ?? 'Unable to create Paystack customer');
            throw new Exception('Failed to create Paystack customer: ' . $message);
        }

        $decoded = $response['data'];
        if (!is_array($decoded) || !isset($decoded['status']) || $decoded['status'] !== true || empty($decoded['data'])) {
            throw new Exception('Unexpected Paystack customer creation response');
        }

        return $decoded['data'];
    }
}

if (!function_exists('nivasityUpdatePaystackCustomer')) {
    function nivasityUpdatePaystackCustomer($customerCode, $user) {
        $customerCode = trim((string)$customerCode);
        if ($customerCode === '') {
            throw new Exception('Unable to update Paystack customer without a customer code');
        }

        $payload = [
            'first_name' => (string)($user['first_name'] ?? ''),
            'last_name' => (string)($user['last_name'] ?? ''),
            'phone' => (string)($user['phone'] ?? ''),
        ];

        $response = nivasityPaystackRequest('PUT', '/customer/' . rawurlencode($customerCode), $payload);
        if (!$response['ok']) {
            $message = nivasityExtractPaystackErrorMessage($response, 'Unable to update Paystack customer');
            throw new Exception('Failed to update Paystack customer: ' . $message);
        }

        $decoded = $response['data'];
        if (!is_array($decoded) || !isset($decoded['status']) || $decoded['status'] !== true || empty($decoded['data'])) {
            throw new Exception('Unexpected Paystack customer update response');
        }

        return $decoded['data'];
    }
}

if (!function_exists('nivasityEnsurePaystackCustomer')) {
    function nivasityEnsurePaystackCustomer($conn, $user) {
        $userId = (int)($user['id'] ?? 0);
        $storedCustomerCode = trim((string)($user['paystack_customer_code'] ?? ''));
        $customer = null;
        $status = 'updated';

        if ($storedCustomerCode !== '') {
            $customer = nivasityFetchPaystackCustomer($storedCustomerCode);
        }

        if (!$customer) {
            $customer = nivasityFetchPaystackCustomerByEmail((string)($user['email'] ?? ''));
        }

        if (!$customer) {
            $customer = nivasityCreatePaystackCustomer($user);
            $status = 'created';
        }

        $customerCode = trim((string)($customer['customer_code'] ?? ''));
        if ($customerCode === '') {
            throw new Exception('Unable to resolve Paystack customer code');
        }

        $customer = nivasityUpdatePaystackCustomer($customerCode, $user);
        nivasityPersistPaystackCustomerDetails($conn, $userId, $customer);

        return [
            'status' => $status,
            'customer' => $customer,
        ];
    }
}

if (!function_exists('nivasityFetchDedicatedAccountForCustomer')) {
    function nivasityFetchDedicatedAccountForCustomer($customer, $preferredBank = 'wema-bank') {
        $customerId = (int)($customer['id'] ?? 0);
        $customerCode = trim((string)($customer['customer_code'] ?? ''));
        if ($customerId <= 0 && $customerCode === '') {
            return null;
        }

        if ($customerCode !== '') {
            $freshCustomer = nivasityFetchPaystackCustomer($customerCode);
            $dedicatedAccount = $freshCustomer['dedicated_account'] ?? null;
            $bankSlug = strtolower(trim((string)($dedicatedAccount['bank']['slug'] ?? '')));

            if (is_array($dedicatedAccount) && !empty($dedicatedAccount['account_number']) && ($preferredBank === '' || $bankSlug === strtolower($preferredBank))) {
                return $dedicatedAccount;
            }
        }

        if ($customerId <= 0) {
            return null;
        }

        $queryParams = [
            'customer' => $customerId,
            'active' => 'true',
            'provider_slug' => $preferredBank,
            'perPage' => 100,
        ];

        $query = http_build_query($queryParams);
        $response = nivasityPaystackRequest('GET', '/dedicated_account?' . $query);
        if (!$response['ok']) {
            $message = nivasityExtractPaystackErrorMessage($response, 'Unable to list Paystack dedicated accounts');
            nivasityWalletLog('Paystack dedicated account lookup failed', [
                'customer_id' => $customerId,
                'query' => $queryParams,
                'message' => $message,
            ]);
            return null;
        }

        $decoded = $response['data'];
        if (!is_array($decoded) || !isset($decoded['status']) || $decoded['status'] !== true || !isset($decoded['data']) || !is_array($decoded['data'])) {
            nivasityWalletLog('Paystack dedicated account lookup returned unexpected response', [
                'customer_id' => $customerId,
                'query' => $queryParams,
                'response' => $decoded,
            ]);
            return null;
        }

        foreach ($decoded['data'] as $account) {
            $accountBankSlug = strtolower(trim((string)($account['bank']['slug'] ?? '')));
            $accountCustomerId = (int)($account['customer']['id'] ?? 0);

            if ($accountCustomerId === $customerId && ($preferredBank === '' || $accountBankSlug === strtolower($preferredBank))) {
                return $account;
            }
        }

        return null;
    }
}

if (!function_exists('nivasityCreateDedicatedAccountForCustomer')) {
    function nivasityCreateDedicatedAccountForCustomer($customer, $user, $preferredBank = 'wema-bank') {
        $customerRef = (string)($customer['customer_code'] ?? $customer['id'] ?? '');
        if ($customerRef === '') {
            throw new Exception('Unable to resolve Paystack customer reference for wallet creation');
        }

        $payload = [
            'customer' => $customerRef,
            'preferred_bank' => $preferredBank,
            'first_name' => (string)($user['first_name'] ?? ''),
            'last_name' => (string)($user['last_name'] ?? ''),
            'phone' => (string)($user['phone'] ?? ''),
        ];

        $response = nivasityPaystackRequest('POST', '/dedicated_account', $payload);
        if (!$response['ok']) {
            $message = nivasityExtractPaystackErrorMessage($response, 'Unable to create Paystack dedicated account');
            throw new Exception('Failed to create Paystack dedicated account: ' . $message);
        }

        $decoded = $response['data'];
        if (!is_array($decoded) || !isset($decoded['status']) || $decoded['status'] !== true || empty($decoded['data']['account_number'])) {
            throw new Exception('Unexpected Paystack dedicated account creation response');
        }

        return [
            'account' => $decoded['data'],
            'raw_response' => $decoded,
        ];
    }
}

if (!function_exists('nivasityPersistWalletFromPaystackData')) {
    function nivasityPersistWalletFromPaystackData($conn, $userId, $requestedVia, $user, $walletData, $rawResponse, $accountSource = 'created') {
        $existingWallet = nivasityGetUserWallet($conn, $userId);
        if ($existingWallet) {
            return [
                'status' => 'exists',
                'wallet' => $existingWallet,
                'account_source' => $accountSource,
            ];
        }

        $userId = (int)$userId;
        $schoolId = (int)($user['school'] ?? 0);
        $requestedViaSafe = mysqli_real_escape_string($conn, strtolower(trim((string)$requestedVia)) ?: 'web');
        $providerAccountId = mysqli_real_escape_string($conn, (string)($walletData['id'] ?? ''));
        $providerCustomerCode = mysqli_real_escape_string($conn, (string)($walletData['customer']['customer_code'] ?? ''));
        $accountName = mysqli_real_escape_string($conn, (string)($walletData['account_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))));
        $accountNumber = mysqli_real_escape_string($conn, (string)($walletData['account_number'] ?? ''));
        $bankName = mysqli_real_escape_string($conn, (string)($walletData['bank']['name'] ?? 'Wema Bank'));
        $bankSlug = mysqli_real_escape_string($conn, (string)($walletData['bank']['slug'] ?? 'wema-bank'));
        $accountStatus = !empty($walletData['active']) || !array_key_exists('active', $walletData) ? 'active' : 'inactive';
        $accountStatusSafe = mysqli_real_escape_string($conn, $accountStatus);
        $rawResponseSafe = mysqli_real_escape_string($conn, json_encode($rawResponse));

        mysqli_begin_transaction($conn);
        try {
            $walletLockSql = "SELECT id FROM user_wallets WHERE user_id = $userId LIMIT 1 FOR UPDATE";
            $walletLockRs = mysqli_query($conn, $walletLockSql);
            if ($walletLockRs && mysqli_num_rows($walletLockRs) > 0) {
                mysqli_commit($conn);
                $existingWallet = nivasityGetUserWallet($conn, $userId);
                return [
                    'status' => 'exists',
                    'wallet' => $existingWallet,
                    'account_source' => $accountSource,
                ];
            }

            $insertWalletSql = "INSERT INTO user_wallets (user_id, school_id, requested_via) VALUES ($userId, $schoolId, '$requestedViaSafe')";
            if (!mysqli_query($conn, $insertWalletSql)) {
                throw new Exception('Failed to create wallet row: ' . mysqli_error($conn));
            }
            $walletId = (int)mysqli_insert_id($conn);

            $insertVaSql = "INSERT INTO wallet_virtual_accounts (
                    wallet_id, provider, provider_account_id, provider_customer_code,
                    account_name, account_number, bank_name, bank_slug, status, raw_response
                ) VALUES (
                    $walletId, 'paystack', '$providerAccountId', '$providerCustomerCode',
                    '$accountName', '$accountNumber', '$bankName', '$bankSlug', '$accountStatusSafe', '$rawResponseSafe'
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
        return [
            'status' => 'created',
            'wallet' => $wallet,
            'account_source' => $accountSource,
        ];
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

        $userSelectFields = 'id, first_name, last_name, email, phone, school, role, status';
        if (nivasityUsersHasPaystackCustomerCodeColumn($conn)) {
            $userSelectFields .= ', paystack_customer_code';
        }
        if (nivasityUsersHasPaystackCustomerIdColumn($conn)) {
            $userSelectFields .= ', paystack_customer_id';
        }

        $userSql = "SELECT $userSelectFields FROM users WHERE id = $userId LIMIT 1";
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

        $preferredBank = nivasityGetPaystackDedicatedAccountPreferredBank();

        $customerResult = nivasityEnsurePaystackCustomer($conn, $user);
        $customer = $customerResult['customer'] ?? null;
        if (!$customer) {
            throw new Exception('Unable to resolve Paystack customer for wallet creation');
        }

        $walletData = nivasityFetchDedicatedAccountForCustomer($customer, $preferredBank);
        $walletSource = 'existing_dva';
        $rawPayload = [
            'status' => true,
            'message' => 'Existing dedicated account reused',
            'data' => $walletData,
            'customer' => $customer,
        ];

        if (!$walletData || empty($walletData['account_number'])) {
            $createdAccount = nivasityCreateDedicatedAccountForCustomer($customer, $user, $preferredBank);
            $walletData = $createdAccount['account'] ?? null;
            $rawPayload = $createdAccount['raw_response'] ?? $rawPayload;
            $walletSource = 'new_dva';
        }

        if (!$walletData || empty($walletData['account_number'])) {
            throw new Exception('Paystack dedicated account could not be resolved for wallet creation');
        }

        $persistResult = nivasityPersistWalletFromPaystackData($conn, $userId, $requestedVia, $user, $walletData, $rawPayload, $walletSource);
        $wallet = $persistResult['wallet'] ?? nivasityGetUserWallet($conn, $userId);

        nivasityWalletLog('Created wallet on explicit request', [
            'user_id' => $userId,
            'requested_via' => $requestedVia,
            'customer_status' => $customerResult['status'] ?? null,
            'wallet_source' => $persistResult['account_source'] ?? $walletSource,
            'account_number' => $wallet['account_number'] ?? null,
        ]);

        return [
            'status' => $persistResult['status'] ?? 'created',
            'wallet' => $wallet,
            'customer_status' => $customerResult['status'] ?? null,
            'wallet_source' => $persistResult['account_source'] ?? $walletSource,
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

if (!function_exists('nivasityCalculateWalletFundingProviderCharge')) {
    function nivasityCalculateWalletFundingProviderCharge($amount, $provider = 'paystack') {
        $amount = (int)round((float)$amount);
        if ($amount <= 0) {
            return 0;
        }

        $provider = strtolower(trim((string)$provider));
        if ($provider === 'paystack') {
            return min(300, (int)round($amount * 0.01));
        }

        return 0;
    }
}

if (!function_exists('nivasityIsPaystackDedicatedNubanPayload')) {
    function nivasityIsPaystackDedicatedNubanPayload($data) {
        if (!is_array($data)) {
            return false;
        }

        $topLevelChannel = strtolower(trim((string)($data['channel'] ?? '')));
        $authChannel = strtolower(trim((string)($data['authorization']['channel'] ?? '')));

        return $topLevelChannel === 'dedicated_nuban' || $authChannel === 'dedicated_nuban';
    }
}

if (!function_exists('nivasityIsLikelyCheckoutReference')) {
    function nivasityIsLikelyCheckoutReference($reference) {
        $reference = trim((string)$reference);
        if ($reference === '') {
            return false;
        }

        return (bool)preg_match('/^nivas_[0-9]+_[0-9]+$/i', $reference);
    }
}

if (!function_exists('nivasityReferenceBelongsToPurchaseFlow')) {
    function nivasityReferenceBelongsToPurchaseFlow($conn, $reference) {
        $reference = trim((string)$reference);
        if ($reference === '') {
            return false;
        }

        if (nivasityIsLikelyCheckoutReference($reference)) {
            return true;
        }

        $referenceSafe = mysqli_real_escape_string($conn, $reference);
        $checks = [
            "SELECT 1 FROM cart WHERE ref_id = '$referenceSafe' LIMIT 1",
            "SELECT 1 FROM manuals_bought WHERE ref_id = '$referenceSafe' LIMIT 1",
            "SELECT 1 FROM event_tickets WHERE ref_id = '$referenceSafe' LIMIT 1",
            "SELECT 1 FROM transactions WHERE ref_id = '$referenceSafe' AND (transaction_context = 'purchase' OR payment_channel = 'gateway') LIMIT 1",
        ];

        foreach ($checks as $sql) {
            $rs = mysqli_query($conn, $sql);
            if ($rs && mysqli_num_rows($rs) > 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('nivasityResolveWalletFromPaystackPayload')) {
    function nivasityResolveWalletFromPaystackPayload($conn, $data, $allowCustomerFallback = true) {
        $strongCandidates = [];

        $accountNumberCandidates = [
            $data['dedicated_account']['account_number'] ?? null,
            $data['authorization']['receiver_bank_account_number'] ?? null,
            $data['customer']['dedicated_account']['account_number'] ?? null,
        ];

        foreach ($accountNumberCandidates as $accountNumber) {
            $accountNumber = trim((string)$accountNumber);
            if ($accountNumber !== '') {
                $safe = mysqli_real_escape_string($conn, $accountNumber);
                $strongCandidates[] = "va.account_number = '$safe'";
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
                $strongCandidates[] = "va.provider_account_id = '$safe'";
            }
        }

        if (!empty($strongCandidates)) {
            $where = implode(' OR ', array_unique($strongCandidates));
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
        }

        if (!$allowCustomerFallback) {
            return null;
        }

        $fallbackCandidates = [];

        $customerCode = trim((string)($data['customer']['customer_code'] ?? ''));
        if ($customerCode !== '') {
            $safe = mysqli_real_escape_string($conn, $customerCode);
            $fallbackCandidates[] = "va.provider_customer_code = '$safe'";
        }

        $email = trim((string)($data['customer']['email'] ?? ''));
        if ($email !== '') {
            $safeEmail = mysqli_real_escape_string($conn, $email);
            $fallbackCandidates[] = "u.email = '$safeEmail'";
        }

        if (empty($fallbackCandidates)) {
            return null;
        }

        $where = implode(' OR ', array_unique($fallbackCandidates));
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
        $providerChargeAmount = nivasityCalculateWalletFundingProviderCharge($amount, 'paystack');
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

            if (
                nivasityWalletFundingTransactionsHasColumn($conn, 'provider_charge_amount')
                && nivasityWalletFundingTransactionsHasColumn($conn, 'consumed_charge_amount')
                && nivasityWalletFundingTransactionsHasColumn($conn, 'remaining_charge_amount')
            ) {
                $insertFundingSql = "INSERT INTO wallet_funding_transactions (
                        wallet_id, user_id, provider, provider_reference, provider_event,
                        provider_transaction_id, provider_account_id, account_number, amount, provider_charge_amount,
                        consumed_charge_amount, remaining_charge_amount,
                        status, source, description, raw_payload, posted_at
                    ) VALUES (
                        $walletId, $userId, 'paystack', '$providerReferenceSafe', '$providerEventSafe',
                        '$providerTransactionIdSafe', '$providerAccountIdSafe', '$accountNumberSafe', $amount, $providerChargeAmount,
                        0, $providerChargeAmount,
                        'posted', '$sourceSafe', '$descriptionSafe', '$rawPayloadSafe', NOW()
                    )";
            } elseif (nivasityWalletFundingTransactionsHasColumn($conn, 'provider_charge_amount')) {
                $insertFundingSql = "INSERT INTO wallet_funding_transactions (
                        wallet_id, user_id, provider, provider_reference, provider_event,
                        provider_transaction_id, provider_account_id, account_number, amount, provider_charge_amount,
                        status, source, description, raw_payload, posted_at
                    ) VALUES (
                        $walletId, $userId, 'paystack', '$providerReferenceSafe', '$providerEventSafe',
                        '$providerTransactionIdSafe', '$providerAccountIdSafe', '$accountNumberSafe', $amount, $providerChargeAmount,
                        'posted', '$sourceSafe', '$descriptionSafe', '$rawPayloadSafe', NOW()
                    )";
            } else {
                $insertFundingSql = "INSERT INTO wallet_funding_transactions (
                        wallet_id, user_id, provider, provider_reference, provider_event,
                        provider_transaction_id, provider_account_id, account_number, amount,
                        status, source, description, raw_payload, posted_at
                    ) VALUES (
                        $walletId, $userId, 'paystack', '$providerReferenceSafe', '$providerEventSafe',
                        '$providerTransactionIdSafe', '$providerAccountIdSafe', '$accountNumberSafe', $amount,
                        'posted', '$sourceSafe', '$descriptionSafe', '$rawPayloadSafe', NOW()
                    )";
            }
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
                                    charge = $providerChargeAmount,
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
                        '$providerReferenceSafe', $userId, $amount, $providerChargeAmount, 0, 0, 'successful', 'PAYSTACK', 'wallet', 'wallet_funding'
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
            'provider_charge_amount' => $providerChargeAmount,
            'source' => $source,
        ]);

        nivasitySendWalletAlert($conn, $userId, 'credit', [
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'reference' => $providerReference,
            'description' => $description,
        ]);

        return [
            'status' => 'posted',
            'amount' => $amount,
        ];
    }
}

if (!function_exists('nivasityWalletFundingChargeTrackingColumnsExist')) {
    function nivasityWalletFundingChargeTrackingColumnsExist($conn) {
        return nivasityWalletFundingTransactionsHasColumn($conn, 'provider_charge_amount')
            && nivasityWalletFundingTransactionsHasColumn($conn, 'consumed_charge_amount')
            && nivasityWalletFundingTransactionsHasColumn($conn, 'remaining_charge_amount');
    }
}

if (!function_exists('nivasityExtractRecoveredFundingChargeFromMetadata')) {
    function nivasityExtractRecoveredFundingChargeFromMetadata($metadata) {
        if (is_string($metadata) && trim($metadata) !== '') {
            $decoded = json_decode($metadata, true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        if (!is_array($metadata)) {
            return 0;
        }

        if (isset($metadata['funding_charge_recovered'])) {
            return max(0, (int)round((float)$metadata['funding_charge_recovered']));
        }

        if (isset($metadata['funding_charge_recovery']) && is_array($metadata['funding_charge_recovery'])) {
            return max(0, (int)round((float)($metadata['funding_charge_recovery']['recovered_amount'] ?? 0)));
        }

        return 0;
    }
}

if (!function_exists('nivasityGetWalletFundingChargeRecoveryState')) {
    function nivasityGetWalletFundingChargeRecoveryState($conn, $walletId, $forUpdate = false) {
        $walletId = (int)$walletId;
        if ($walletId <= 0) {
            return [
                'total_provider_charge' => 0,
                'recovered_provider_charge' => 0,
                'outstanding_provider_charge' => 0,
            ];
        }

        if (nivasityWalletFundingChargeTrackingColumnsExist($conn)) {
            nivasitySyncWalletFundingChargeTracking($conn, $walletId, $forUpdate);

            $suffix = $forUpdate ? ' FOR UPDATE' : '';
            $trackingSql = "SELECT
                                COALESCE(SUM(provider_charge_amount), 0) AS total_provider_charge,
                                COALESCE(SUM(consumed_charge_amount), 0) AS recovered_provider_charge,
                                COALESCE(SUM(remaining_charge_amount), 0) AS outstanding_provider_charge
                            FROM wallet_funding_transactions
                            WHERE wallet_id = $walletId AND status = 'posted'$suffix";
            $trackingRs = mysqli_query($conn, $trackingSql);
            if (!$trackingRs) {
                throw new Exception('Failed to inspect wallet funding charge tracking: ' . mysqli_error($conn));
            }

            $trackingRow = mysqli_fetch_assoc($trackingRs) ?: [];
            return [
                'total_provider_charge' => max(0, (int)($trackingRow['total_provider_charge'] ?? 0)),
                'recovered_provider_charge' => max(0, (int)($trackingRow['recovered_provider_charge'] ?? 0)),
                'outstanding_provider_charge' => max(0, (int)($trackingRow['outstanding_provider_charge'] ?? 0)),
            ];
        }

        $hasProviderChargeColumn = nivasityWalletFundingTransactionsHasColumn($conn, 'provider_charge_amount');
        $fundingFields = $hasProviderChargeColumn
            ? 'amount, provider, provider_charge_amount'
            : 'amount, provider';
        $fundingSql = "SELECT $fundingFields FROM wallet_funding_transactions WHERE wallet_id = $walletId AND status = 'posted'";
        $fundingRs = mysqli_query($conn, $fundingSql);
        if (!$fundingRs) {
            throw new Exception('Failed to inspect wallet funding charges: ' . mysqli_error($conn));
        }

        $totalProviderCharge = 0;
        while ($fundingRow = mysqli_fetch_assoc($fundingRs)) {
            $provider = trim((string)($fundingRow['provider'] ?? 'paystack'));
            $amount = (int)round((float)($fundingRow['amount'] ?? 0));
            $providerChargeAmount = $hasProviderChargeColumn
                ? (int)round((float)($fundingRow['provider_charge_amount'] ?? 0))
                : 0;

            if ($providerChargeAmount <= 0 && $amount > 0) {
                $providerChargeAmount = nivasityCalculateWalletFundingProviderCharge($amount, $provider);
            }

            $totalProviderCharge += max(0, $providerChargeAmount);
        }

        $ledgerSql = "SELECT metadata FROM wallet_ledger_entries WHERE wallet_id = $walletId AND entry_type = 'debit' AND reference LIKE 'wallet_purchase:%'";
        $ledgerRs = mysqli_query($conn, $ledgerSql);
        if (!$ledgerRs) {
            throw new Exception('Failed to inspect wallet charge recovery history: ' . mysqli_error($conn));
        }

        $recoveredProviderCharge = 0;
        while ($ledgerRow = mysqli_fetch_assoc($ledgerRs)) {
            $recoveredProviderCharge += nivasityExtractRecoveredFundingChargeFromMetadata($ledgerRow['metadata'] ?? '');
        }

        $recoveredProviderCharge = min($recoveredProviderCharge, $totalProviderCharge);

        return [
            'total_provider_charge' => $totalProviderCharge,
            'recovered_provider_charge' => $recoveredProviderCharge,
            'outstanding_provider_charge' => max(0, $totalProviderCharge - $recoveredProviderCharge),
        ];
    }
}

if (!function_exists('nivasitySyncWalletFundingChargeTracking')) {
    function nivasitySyncWalletFundingChargeTracking($conn, $walletId, $forUpdate = false) {
        $walletId = (int)$walletId;
        if ($walletId <= 0 || !nivasityWalletFundingChargeTrackingColumnsExist($conn)) {
            return [
                'total_provider_charge' => 0,
                'recovered_provider_charge' => 0,
                'outstanding_provider_charge' => 0,
            ];
        }

        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        $fundingSql = "SELECT id, amount, provider, provider_charge_amount, consumed_charge_amount, remaining_charge_amount
                       FROM wallet_funding_transactions
                       WHERE wallet_id = $walletId AND status = 'posted'
                       ORDER BY posted_at ASC, id ASC$suffix";
        $fundingRs = mysqli_query($conn, $fundingSql);
        if (!$fundingRs) {
            throw new Exception('Failed to read wallet funding rows for charge tracking sync: ' . mysqli_error($conn));
        }

        $fundingRows = [];
        $totalProviderCharge = 0;
        while ($fundingRow = mysqli_fetch_assoc($fundingRs)) {
            $provider = trim((string)($fundingRow['provider'] ?? 'paystack'));
            $amount = (int)round((float)($fundingRow['amount'] ?? 0));
            $providerChargeAmount = (int)round((float)($fundingRow['provider_charge_amount'] ?? 0));
            if ($providerChargeAmount <= 0 && $amount > 0) {
                $providerChargeAmount = nivasityCalculateWalletFundingProviderCharge($amount, $provider);
            }

            $fundingRow['provider_charge_amount'] = max(0, $providerChargeAmount);
            $fundingRows[] = $fundingRow;
            $totalProviderCharge += (int)$fundingRow['provider_charge_amount'];
        }

        $ledgerSql = "SELECT metadata FROM wallet_ledger_entries WHERE wallet_id = $walletId AND entry_type = 'debit' AND reference LIKE 'wallet_purchase:%'";
        $ledgerRs = mysqli_query($conn, $ledgerSql);
        if (!$ledgerRs) {
            throw new Exception('Failed to inspect wallet charge recovery history: ' . mysqli_error($conn));
        }

        $recoveredProviderCharge = 0;
        while ($ledgerRow = mysqli_fetch_assoc($ledgerRs)) {
            $recoveredProviderCharge += nivasityExtractRecoveredFundingChargeFromMetadata($ledgerRow['metadata'] ?? '');
        }
        $recoveredProviderCharge = min($recoveredProviderCharge, $totalProviderCharge);

        $remainingToAllocate = $recoveredProviderCharge;
        foreach ($fundingRows as $fundingRow) {
            $fundingId = (int)($fundingRow['id'] ?? 0);
            $providerChargeAmount = (int)($fundingRow['provider_charge_amount'] ?? 0);
            $expectedConsumed = min($providerChargeAmount, $remainingToAllocate);
            $expectedRemaining = max(0, $providerChargeAmount - $expectedConsumed);
            $remainingToAllocate = max(0, $remainingToAllocate - $expectedConsumed);

            $currentConsumed = max(0, (int)($fundingRow['consumed_charge_amount'] ?? 0));
            $currentRemaining = max(0, (int)($fundingRow['remaining_charge_amount'] ?? 0));

            if (
                $currentConsumed !== $expectedConsumed
                || $currentRemaining !== $expectedRemaining
                || (int)($fundingRow['provider_charge_amount'] ?? 0) !== $providerChargeAmount
            ) {
                $updateSql = "UPDATE wallet_funding_transactions
                              SET provider_charge_amount = $providerChargeAmount,
                                  consumed_charge_amount = $expectedConsumed,
                                  remaining_charge_amount = $expectedRemaining,
                                  updated_at = NOW()
                              WHERE id = $fundingId LIMIT 1";
                if (!mysqli_query($conn, $updateSql)) {
                    throw new Exception('Failed to sync wallet funding charge tracking: ' . mysqli_error($conn));
                }
            }
        }

        return [
            'total_provider_charge' => $totalProviderCharge,
            'recovered_provider_charge' => $recoveredProviderCharge,
            'outstanding_provider_charge' => max(0, $totalProviderCharge - $recoveredProviderCharge),
        ];
    }
}

if (!function_exists('nivasityConsumeWalletFundingChargeTracking')) {
    function nivasityConsumeWalletFundingChargeTracking($conn, $walletId, $amountToRecover) {
        $walletId = (int)$walletId;
        $amountToRecover = max(0, (int)$amountToRecover);
        if ($walletId <= 0 || $amountToRecover <= 0 || !nivasityWalletFundingChargeTrackingColumnsExist($conn)) {
            return 0;
        }

        nivasitySyncWalletFundingChargeTracking($conn, $walletId, true);

        $fundingSql = "SELECT id, provider_charge_amount, consumed_charge_amount, remaining_charge_amount
                       FROM wallet_funding_transactions
                       WHERE wallet_id = $walletId AND status = 'posted' AND remaining_charge_amount > 0
                       ORDER BY posted_at ASC, id ASC FOR UPDATE";
        $fundingRs = mysqli_query($conn, $fundingSql);
        if (!$fundingRs) {
            throw new Exception('Failed to load wallet funding rows for charge consumption: ' . mysqli_error($conn));
        }

        $recovered = 0;
        while ($amountToRecover > 0 && ($fundingRow = mysqli_fetch_assoc($fundingRs))) {
            $fundingId = (int)($fundingRow['id'] ?? 0);
            $providerChargeAmount = max(0, (int)($fundingRow['provider_charge_amount'] ?? 0));
            $currentConsumed = max(0, (int)($fundingRow['consumed_charge_amount'] ?? 0));
            $currentRemaining = max(0, (int)($fundingRow['remaining_charge_amount'] ?? 0));
            if ($currentRemaining <= 0) {
                continue;
            }

            $consumeNow = min($currentRemaining, $amountToRecover);
            $newConsumed = min($providerChargeAmount, $currentConsumed + $consumeNow);
            $newRemaining = max(0, $providerChargeAmount - $newConsumed);
            $updateSql = "UPDATE wallet_funding_transactions
                          SET consumed_charge_amount = $newConsumed,
                              remaining_charge_amount = $newRemaining,
                              updated_at = NOW()
                          WHERE id = $fundingId LIMIT 1";
            if (!mysqli_query($conn, $updateSql)) {
                throw new Exception('Failed to consume wallet funding charge tracking: ' . mysqli_error($conn));
            }

            $amountToRecover -= $consumeNow;
            $recovered += $consumeNow;
        }

        return $recovered;
    }
}

if (!function_exists('nivasityResolveUserPaystackCustomer')) {
    function nivasityResolveUserPaystackCustomer($conn, $userId) {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return null;
        }

        $fields = 'id, email';
        if (nivasityUsersHasPaystackCustomerCodeColumn($conn)) {
            $fields .= ', paystack_customer_code';
        }
        if (nivasityUsersHasPaystackCustomerIdColumn($conn)) {
            $fields .= ', paystack_customer_id';
        }

        $sql = "SELECT $fields FROM users WHERE id = $userId LIMIT 1";
        $rs = mysqli_query($conn, $sql);
        if (!$rs || mysqli_num_rows($rs) < 1) {
            return null;
        }

        $user = mysqli_fetch_assoc($rs);
        $customerId = (int)($user['paystack_customer_id'] ?? 0);
        $customerCode = trim((string)($user['paystack_customer_code'] ?? ''));
        if ($customerId > 0 && $customerCode !== '') {
            return [
                'id' => $customerId,
                'customer_code' => $customerCode,
                'email' => (string)($user['email'] ?? ''),
            ];
        }

        $customer = null;
        if ($customerCode !== '') {
            $customer = nivasityFetchPaystackCustomer($customerCode);
        }
        if (!$customer && !empty($user['email'])) {
            $customer = nivasityFetchPaystackCustomerByEmail((string)$user['email']);
        }
        if ($customer) {
            nivasityPersistPaystackCustomerDetails($conn, $userId, $customer);
        }

        return $customer;
    }
}

if (!function_exists('nivasityListPaystackTransactionsForCustomer')) {
    function nivasityListPaystackTransactionsForCustomer($customerId, $fromDate = null, $status = 'success', $perPage = 100) {
        $customerId = (int)$customerId;
        if ($customerId <= 0) {
            return [];
        }
        $queryParams = [
            'customer' => $customerId,
            'perPage' => max(1, (int)$perPage),
        ];
        if (trim((string)$status) !== '') {
            $queryParams['status'] = trim((string)$status);
        }
        if ($fromDate !== null && trim((string)$fromDate) !== '') {
            $queryParams['from'] = trim((string)$fromDate);
        }

        $response = nivasityPaystackRequest('GET', '/transaction?' . http_build_query($queryParams));
        if (!$response['ok']) {
            $message = nivasityExtractPaystackErrorMessage($response, 'Unable to fetch Paystack transactions');
            throw new Exception('Failed to fetch Paystack transactions: ' . $message);
        }

        $decoded = $response['data'];
        if (!is_array($decoded) || !isset($decoded['status']) || $decoded['status'] !== true || !isset($decoded['data']) || !is_array($decoded['data'])) {
            throw new Exception('Unexpected Paystack transaction list response');
        }

        return $decoded['data'];
    }
}

if (!function_exists('nivasityIsPaystackDvaTransaction')) {
    function nivasityIsPaystackDvaTransaction($conn, $wallet, $transaction, $customerId, $customerCode = '') {
        if (!is_array($transaction) || strtolower((string)($transaction['status'] ?? '')) !== 'success') {
            return false;
        }

        $reference = trim((string)($transaction['reference'] ?? ''));
        if ($reference !== '' && nivasityReferenceBelongsToPurchaseFlow($conn, $reference)) {
            return false;
        }

        $txCustomerId = (int)($transaction['customer']['id'] ?? 0);
        if ($customerId > 0 && $txCustomerId > 0 && $txCustomerId !== (int)$customerId) {
            return false;
        }

        $txCustomerCode = trim((string)($transaction['customer']['customer_code'] ?? ''));
        if ($customerCode !== '' && $txCustomerCode !== '' && $txCustomerCode !== $customerCode) {
            return false;
        }

        $walletAccountNumber = trim((string)($wallet['account_number'] ?? ''));
        $walletProviderAccountId = trim((string)($wallet['provider_account_id'] ?? ''));
        $topLevelChannel = strtolower(trim((string)($transaction['channel'] ?? '')));
        $authCardType = strtolower(trim((string)($transaction['authorization']['card_type'] ?? '')));
        $authBrand = strtolower(trim((string)($transaction['authorization']['brand'] ?? '')));
        $receiverAccountNumber = trim((string)($transaction['authorization']['receiver_bank_account_number'] ?? ''));
        $dedicatedAccountNumber = trim((string)($transaction['dedicated_account']['account_number'] ?? $transaction['customer']['dedicated_account']['account_number'] ?? ''));
        $dedicatedAccountId = trim((string)($transaction['dedicated_account']['id'] ?? $transaction['customer']['dedicated_account']['id'] ?? ''));

        if ($walletProviderAccountId !== '' && $dedicatedAccountId !== '' && $walletProviderAccountId === $dedicatedAccountId) {
            return true;
        }

        if ($walletAccountNumber !== '' && $dedicatedAccountNumber !== '' && $walletAccountNumber === $dedicatedAccountNumber) {
            return true;
        }

        if (nivasityIsPaystackDedicatedNubanPayload($transaction)) {
            return true;
        }

        if ($topLevelChannel === 'bank_transfer' && $walletAccountNumber !== '' && $receiverAccountNumber !== '' && $walletAccountNumber === $receiverAccountNumber) {
            return true;
        }

        if ($authBrand === 'managed account' && $authCardType === 'transfer') {
            return true;
        }

        return false;
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

        $customer = nivasityResolveUserPaystackCustomer($conn, $userId);
        $customerId = (int)($customer['id'] ?? 0);
        if ($customerId <= 0) {
            throw new Exception('Unable to resolve Paystack customer id for wallet funding sync');
        }

        $customerCode = trim((string)($customer['customer_code'] ?? ''));
        $fromDate = !empty($wallet['created_at']) ? date('Y-m-d', strtotime((string)$wallet['created_at'])) : null;
        $transactions = nivasityListPaystackTransactionsForCustomer($customerId, $fromDate, 'success', 100);

        $processed = 0;
        $posted = 0;
        foreach ($transactions as $row) {
            if (!nivasityIsPaystackDvaTransaction($conn, $wallet, $row, $customerId, $customerCode)) {
                continue;
            }

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
            'message' => $posted > 0 ? 'Wallet funding sync completed successfully.' : 'No new DVA funding transactions were found for this customer.',
        ];
    }
}

if (!function_exists('nivasityDeterminePaystackWebhookIntent')) {
    function nivasityDeterminePaystackWebhookIntent($conn, $payload) {
        $data = $payload['data'] ?? [];
        $reference = trim((string)($data['reference'] ?? ''));

        if ($reference !== '' && nivasityReferenceBelongsToPurchaseFlow($conn, $reference)) {
            return 'purchase';
        }

        if (nivasityIsPaystackDedicatedNubanPayload($data)) {
            return 'wallet_funding';
        }

        $wallet = nivasityResolveWalletFromPaystackPayload($conn, $data, false);
        if ($wallet) {
            return 'wallet_funding';
        }

        return 'unknown';
    }
}

if (!function_exists('nivasityCreditWalletRefund')) {
    function nivasityCreditWalletRefund($conn, $payload) {
        $userId = (int)($payload['user_id'] ?? 0);
        $refundId = (int)($payload['refund_id'] ?? 0);
        $sourceRefId = trim((string)($payload['source_ref_id'] ?? ''));
        $amount = (int)round((float)($payload['amount'] ?? 0));
        $description = trim((string)($payload['description'] ?? 'Wallet refund'));
        $metadata = $payload['metadata'] ?? [];

        if ($userId <= 0 || $refundId <= 0 || $sourceRefId === '' || $amount <= 0) {
            throw new Exception('Invalid wallet refund payload');
        }

        $wallet = nivasityGetUserWallet($conn, $userId);
        if (!$wallet || (int)($wallet['id'] ?? 0) <= 0) {
            throw new Exception('User wallet not found for refund');
        }

        $walletId = (int)$wallet['id'];
        $reference = 'wallet_refund:' . $refundId;
        $referenceSafe = mysqli_real_escape_string($conn, $reference);
        $existingSql = "SELECT id FROM wallet_ledger_entries WHERE wallet_id = $walletId AND reference = '$referenceSafe' LIMIT 1";
        $existingRs = mysqli_query($conn, $existingSql);
        if ($existingRs && mysqli_num_rows($existingRs) > 0) {
            return [
                'status' => 'exists',
                'wallet_id' => $walletId,
                'amount' => $amount,
            ];
        }

        $walletLockSql = "SELECT balance FROM user_wallets WHERE id = $walletId LIMIT 1 FOR UPDATE";
        $walletLockRs = mysqli_query($conn, $walletLockSql);
        if (!$walletLockRs || mysqli_num_rows($walletLockRs) < 1) {
            throw new Exception('Unable to lock wallet for refund');
        }

        $walletRow = mysqli_fetch_assoc($walletLockRs);
        $balanceBefore = (int)($walletRow['balance'] ?? 0);
        $balanceAfter = $balanceBefore + $amount;
        $sourceRefSafe = mysqli_real_escape_string($conn, $sourceRefId);
        $descriptionSafe = mysqli_real_escape_string($conn, $description);
        $metadataSafe = mysqli_real_escape_string($conn, json_encode($metadata));

        $insertLedgerSql = "INSERT INTO wallet_ledger_entries (
                wallet_id, entry_type, amount, balance_before, balance_after, status,
                reference, provider_reference, description, metadata
            ) VALUES (
                $walletId, 'refund', $amount, $balanceBefore, $balanceAfter, 'posted',
                '$referenceSafe', '$sourceRefSafe', '$descriptionSafe', '$metadataSafe'
            )";
        if (!mysqli_query($conn, $insertLedgerSql)) {
            throw new Exception('Failed to insert wallet refund ledger entry: ' . mysqli_error($conn));
        }

        $updateWalletSql = "UPDATE user_wallets SET balance = $balanceAfter, updated_at = NOW() WHERE id = $walletId";
        if (!mysqli_query($conn, $updateWalletSql)) {
            throw new Exception('Failed to apply wallet refund balance: ' . mysqli_error($conn));
        }

        nivasityWalletLog('Credited wallet refund', [
            'wallet_id' => $walletId,
            'user_id' => $userId,
            'refund_id' => $refundId,
            'source_ref_id' => $sourceRefId,
            'amount' => $amount,
        ]);

        nivasitySendWalletAlert($conn, $userId, 'refund', [
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'reference' => $sourceRefId,
            'description' => $description,
        ]);

        return [
            'status' => 'credited',
            'wallet_id' => $walletId,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
        ];
    }
}

if (!function_exists('nivasityAdjustSchoolPayableForRefund')) {
    function nivasityAdjustSchoolPayableForRefund($conn, $sourceRefId, $refundAmount, $metadata = []) {
        $sourceRefId = trim((string)$sourceRefId);
        $refundAmount = (int)round((float)$refundAmount);
        if ($sourceRefId === '' || $refundAmount <= 0) {
            return [
                'status' => 'ignored',
                'refunded' => 0,
            ];
        }

        $sourceRefSafe = mysqli_real_escape_string($conn, $sourceRefId);
        $ledgerSql = "SELECT * FROM school_payable_ledger WHERE source_ref_id = '$sourceRefSafe' LIMIT 1 FOR UPDATE";
        $ledgerRs = mysqli_query($conn, $ledgerSql);
        if (!$ledgerRs || mysqli_num_rows($ledgerRs) < 1) {
            return [
                'status' => 'missing',
                'refunded' => 0,
            ];
        }

        $ledgerRow = mysqli_fetch_assoc($ledgerRs);
        $ledgerId = (int)($ledgerRow['id'] ?? 0);
        $schoolId = (int)($ledgerRow['school_id'] ?? 0);
        $itemSubtotal = (int)($ledgerRow['item_subtotal'] ?? 0);
        $existingRefundAmount = (int)($ledgerRow['refund_amount'] ?? 0);
        $existingPayableAmount = (int)($ledgerRow['payable_amount'] ?? 0);
        $settledAmount = (int)($ledgerRow['settled_amount'] ?? 0);
        $existingCarryForward = (int)($ledgerRow['carry_forward_amount'] ?? 0);

        $newRefundAmount = min($itemSubtotal, $existingRefundAmount + $refundAmount);
        $effectiveDelta = max(0, $newRefundAmount - $existingRefundAmount);
        if ($effectiveDelta <= 0) {
            return [
                'status' => 'exists',
                'refunded' => 0,
            ];
        }

        $newPayableAmount = max(0, $itemSubtotal - $newRefundAmount);
        $payableReduction = max(0, $existingPayableAmount - $newPayableAmount);
        $carryForwardDelta = max(0, $settledAmount - $newPayableAmount);
        $pendingReduction = max(0, $payableReduction - $carryForwardDelta);

        if ($schoolId > 0 && ($pendingReduction > 0 || $carryForwardDelta > 0)) {
            $walletSql = "SELECT * FROM school_internal_wallets WHERE school_id = $schoolId LIMIT 1 FOR UPDATE";
            $walletRs = mysqli_query($conn, $walletSql);
            if ($walletRs && mysqli_num_rows($walletRs) > 0) {
                $walletRow = mysqli_fetch_assoc($walletRs);
                $currentBalance = (int)($walletRow['current_balance'] ?? 0);
                $pendingBalance = (int)($walletRow['pending_payout_balance'] ?? 0);
                $carryForwardBalance = (int)($walletRow['carry_forward_balance'] ?? 0);
                $newCurrentBalance = max(0, $currentBalance - $pendingReduction);
                $newPendingBalance = max(0, $pendingBalance - $pendingReduction);
                $newCarryForwardBalance = $carryForwardBalance + $carryForwardDelta;

                $updateSchoolWalletSql = "UPDATE school_internal_wallets
                                          SET current_balance = $newCurrentBalance,
                                              pending_payout_balance = $newPendingBalance,
                                              carry_forward_balance = $newCarryForwardBalance,
                                              updated_at = NOW()
                                          WHERE school_id = $schoolId";
                if (!mysqli_query($conn, $updateSchoolWalletSql)) {
                    throw new Exception('Failed to adjust school wallet for refund: ' . mysqli_error($conn));
                }
            }
        }

        $mergedMetadata = [];
        $existingMetadata = json_decode((string)($ledgerRow['metadata'] ?? ''), true);
        if (is_array($existingMetadata)) {
            $mergedMetadata = $existingMetadata;
        }
        $mergedMetadata['refund_adjustment'] = array_merge([
            'source' => 'wallet_refund',
            'refund_amount_delta' => $effectiveDelta,
        ], is_array($metadata) ? $metadata : []);
        $metadataSafe = mysqli_real_escape_string($conn, json_encode($mergedMetadata));
        $newCarryForwardAmount = $existingCarryForward + $carryForwardDelta;

        if ($newPayableAmount <= 0 && $newCarryForwardAmount > 0) {
            $newStatus = 'carry_forward';
        } elseif ($settledAmount > 0 && $settledAmount < $newPayableAmount) {
            $newStatus = 'partially_settled';
        } elseif ($settledAmount >= $newPayableAmount && $newPayableAmount > 0) {
            $newStatus = 'settled';
        } else {
            $newStatus = 'pending';
        }

        $updateLedgerSql = "UPDATE school_payable_ledger
                            SET refund_amount = $newRefundAmount,
                                payable_amount = $newPayableAmount,
                                carry_forward_amount = $newCarryForwardAmount,
                                status = '$newStatus',
                                metadata = '$metadataSafe',
                                updated_at = NOW()
                            WHERE id = $ledgerId";
        if (!mysqli_query($conn, $updateLedgerSql)) {
            throw new Exception('Failed to adjust school payable ledger for refund: ' . mysqli_error($conn));
        }

        nivasityWalletLog('Adjusted school payable for refund', [
            'source_ref_id' => $sourceRefId,
            'school_id' => $schoolId,
            'refund_delta' => $effectiveDelta,
            'new_payable_amount' => $newPayableAmount,
            'carry_forward_delta' => $carryForwardDelta,
        ]);

        return [
            'status' => 'adjusted',
            'refunded' => $effectiveDelta,
            'new_payable_amount' => $newPayableAmount,
            'carry_forward_delta' => $carryForwardDelta,
        ];
    }
}

if (!function_exists('nivasityProcessWalletCheckout')) {
    function nivasityProcessWalletCheckout($conn, $refId, $userId, $sourceChannel = 'web', $walletPin = null) {
        $refId = trim((string)$refId);
        $refIdSafe = mysqli_real_escape_string($conn, $refId);
        $userId = (int)$userId;
        $sourceChannel = strtolower(trim((string)$sourceChannel));
        $sourceChannel = $sourceChannel !== '' ? $sourceChannel : 'web';

        if ($refId === '' || $userId <= 0) {
            throw new Exception('Invalid wallet checkout request');
        }

        nivasityVerifyWalletPin($conn, $userId, $walletPin);

        return withTxProcessingLock($conn, $refId, function() use ($conn, $refId, $refIdSafe, $userId, $sourceChannel) {
            $wallet = nivasityGetUserWallet($conn, $userId);
            if (!$wallet || (int)($wallet['id'] ?? 0) <= 0) {
                throw new Exception('Create your Nivasity Wallet before paying with wallet');
            }
            if ((string)($wallet['status'] ?? 'active') !== 'active') {
                throw new Exception('Your Nivasity Wallet is not active');
            }

            $processedQuery = mysqli_query($conn, "SELECT id, amount FROM transactions WHERE ref_id = '$refIdSafe' ORDER BY id DESC LIMIT 1");
            $txExists = $processedQuery && mysqli_num_rows($processedQuery) > 0;
            $txRow = $txExists ? mysqli_fetch_assoc($processedQuery) : null;
            $manualCountRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM manuals_bought WHERE ref_id = '$refIdSafe' AND buyer = $userId"));
            $eventCountRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM event_tickets WHERE ref_id = '$refIdSafe' AND buyer = $userId"));
            $deliveryCount = (int)($manualCountRow['c'] ?? 0) + (int)($eventCountRow['c'] ?? 0);
            $cartCountRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM cart WHERE ref_id = '$refIdSafe' AND user_id = $userId"));
            $cartCount = (int)($cartCountRow['c'] ?? 0);

            if (($deliveryCount > 0) && ($cartCount <= 0 || $deliveryCount >= $cartCount)) {
                $refundApplied = consumeReservationsForSettledTx($conn, $refId);
                mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$refIdSafe'");
                return [
                    'status' => 'success',
                    'already_processed' => true,
                    'total_amount' => $txRow && isset($txRow['amount']) ? (float)$txRow['amount'] : 0,
                    'refund_applied' => (int)$refundApplied,
                    'manual_ids' => [],
                    'event_ids' => [],
                    'wallet_balance_after' => (int)($wallet['balance'] ?? 0),
                ];
            }

            mysqli_begin_transaction($conn);
            try {
                $walletLockSql = "SELECT id, school_id, balance, status FROM user_wallets WHERE user_id = $userId LIMIT 1 FOR UPDATE";
                $walletLockRs = mysqli_query($conn, $walletLockSql);
                if (!$walletLockRs || mysqli_num_rows($walletLockRs) < 1) {
                    throw new Exception('Wallet not found for checkout');
                }

                $walletRow = mysqli_fetch_assoc($walletLockRs);
                if ((string)($walletRow['status'] ?? 'active') !== 'active') {
                    throw new Exception('Your Nivasity Wallet is not active');
                }

                $walletId = (int)($walletRow['id'] ?? 0);
                $schoolId = (int)($walletRow['school_id'] ?? 0);
                $balanceBefore = (int)($walletRow['balance'] ?? 0);

                $cartQuery = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$refIdSafe' AND user_id = $userId FOR UPDATE");
                if (!$cartQuery || mysqli_num_rows($cartQuery) < 1) {
                    throw new Exception('Cart data not found for wallet checkout');
                }

                $manualIds = [];
                $eventIds = [];
                $sumAmount = 0;
                $itemsProcessed = 0;
                $status = 'successful';

                while ($row = mysqli_fetch_assoc($cartQuery)) {
                    $itemId = (int)$row['item_id'];
                    $type = (string)$row['type'];

                    if ($type === 'manual') {
                        $manualRs = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $itemId AND school_id = $schoolId LIMIT 1");
                        if (!$manualRs || mysqli_num_rows($manualRs) < 1) {
                            throw new Exception('Unable to resolve a material in wallet checkout');
                        }

                        $manualRow = mysqli_fetch_assoc($manualRs);
                        $price = (int)round((float)$manualRow['price']);
                        $sellerId = (int)$manualRow['user_id'];
                        $sumAmount += $price;
                        $manualIds[] = $itemId;

                        $existsRs = mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$refIdSafe' AND manual_id = $itemId AND buyer = $userId LIMIT 1");
                        if (!$existsRs) {
                            throw new Exception('Failed to validate material purchase state');
                        }
                        if (mysqli_num_rows($existsRs) < 1) {
                            $insertManualSql = "INSERT INTO manuals_bought (manual_id, price, seller, buyer, ref_id, status, school_id) VALUES ($itemId, $price, $sellerId, $userId, '$refIdSafe', '$status', $schoolId)";
                            if (!mysqli_query($conn, $insertManualSql)) {
                                throw new Exception('Failed to deliver material with wallet checkout: ' . mysqli_error($conn));
                            }
                        }
                        $itemsProcessed++;
                    } elseif ($type === 'event') {
                        $eventRs = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $itemId LIMIT 1");
                        if (!$eventRs || mysqli_num_rows($eventRs) < 1) {
                            throw new Exception('Unable to resolve an event in wallet checkout');
                        }

                        $eventRow = mysqli_fetch_assoc($eventRs);
                        $price = (int)round((float)$eventRow['price']);
                        $sellerId = (int)$eventRow['user_id'];
                        $sumAmount += $price;
                        $eventIds[] = $itemId;

                        $existsRs = mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$refIdSafe' AND event_id = $itemId AND buyer = $userId LIMIT 1");
                        if (!$existsRs) {
                            throw new Exception('Failed to validate event ticket state');
                        }
                        if (mysqli_num_rows($existsRs) < 1) {
                            $insertEventSql = "INSERT INTO event_tickets (event_id, price, seller, buyer, ref_id, status) VALUES ($itemId, $price, $sellerId, $userId, '$refIdSafe', '$status')";
                            if (!mysqli_query($conn, $insertEventSql)) {
                                throw new Exception('Failed to deliver event ticket with wallet checkout: ' . mysqli_error($conn));
                            }
                        }
                        $itemsProcessed++;
                    }
                }

                if ($itemsProcessed < 1 || $sumAmount <= 0) {
                    throw new Exception('No cart items were fulfilled for wallet checkout');
                }

                $gatewayCharges = function_exists('calculateGatewayCharges') ? calculateGatewayCharges($sumAmount) : ['charge' => 0];
                $walletFee = nivasityGetWalletHandlingFeeBreakdown($conn, $sumAmount, (int)($gatewayCharges['charge'] ?? 0));
                $charge = (int)($walletFee['charge'] ?? 0);
                $chargeRecoveryState = nivasityGetWalletFundingChargeRecoveryState($conn, $walletId, true);
                $fundingChargeOutstandingBefore = (int)($chargeRecoveryState['outstanding_provider_charge'] ?? 0);
                $fundingChargeRecovered = min($charge, $fundingChargeOutstandingBefore);
                if ($fundingChargeRecovered > 0 && nivasityWalletFundingChargeTrackingColumnsExist($conn)) {
                    $fundingChargeRecovered = nivasityConsumeWalletFundingChargeTracking($conn, $walletId, $fundingChargeRecovered);
                }
                $profit = max(0, $charge - $fundingChargeRecovered);
                $totalAmount = (int)($walletFee['total_amount'] ?? $sumAmount);
                if ($balanceBefore < $totalAmount) {
                    throw new Exception('Insufficient wallet balance for this purchase');
                }

                $refundApplied = consumeReservationsCore($conn, $refId);
                $balanceAfter = $balanceBefore - $totalAmount;
                $ledgerReference = mysqli_real_escape_string($conn, 'wallet_purchase:' . $refId);
                $descriptionSafe = mysqli_real_escape_string($conn, 'Wallet purchase');
                $metadataSafe = mysqli_real_escape_string($conn, json_encode([
                    'source_ref_id' => $refId,
                    'source_channel' => $sourceChannel,
                    'manual_ids' => $manualIds,
                    'event_ids' => $eventIds,
                    'funding_charge_recovered' => $fundingChargeRecovered,
                    'funding_charge_recovery' => [
                        'outstanding_before' => $fundingChargeOutstandingBefore,
                        'recovered_amount' => $fundingChargeRecovered,
                        'outstanding_after' => max(0, $fundingChargeOutstandingBefore - $fundingChargeRecovered),
                        'charge_amount' => $charge,
                        'profit_amount' => $profit,
                        'total_provider_charge' => (int)($chargeRecoveryState['total_provider_charge'] ?? 0),
                        'recovered_before' => (int)($chargeRecoveryState['recovered_provider_charge'] ?? 0),
                        'recovered_after' => (int)($chargeRecoveryState['recovered_provider_charge'] ?? 0) + $fundingChargeRecovered,
                    ],
                ]));

                $insertLedgerSql = "INSERT INTO wallet_ledger_entries (
                        wallet_id, entry_type, amount, balance_before, balance_after, status,
                        reference, provider_reference, description, metadata
                    ) VALUES (
                        $walletId, 'debit', $totalAmount, $balanceBefore, $balanceAfter, 'posted',
                        '$ledgerReference', '$refIdSafe', '$descriptionSafe', '$metadataSafe'
                    )";
                if (!mysqli_query($conn, $insertLedgerSql)) {
                    throw new Exception('Failed to record wallet debit: ' . mysqli_error($conn));
                }

                $updateWalletSql = "UPDATE user_wallets SET balance = $balanceAfter, updated_at = NOW() WHERE id = $walletId";
                if (!mysqli_query($conn, $updateWalletSql)) {
                    throw new Exception('Failed to debit wallet balance: ' . mysqli_error($conn));
                }

                $existingTxRs = mysqli_query($conn, "SELECT id FROM transactions WHERE ref_id = '$refIdSafe' ORDER BY id DESC LIMIT 1 FOR UPDATE");
                if (!$existingTxRs) {
                    throw new Exception('Failed to inspect wallet purchase transaction: ' . mysqli_error($conn));
                }
                if (mysqli_num_rows($existingTxRs) > 0) {
                    $updateTxSql = "UPDATE transactions
                                    SET user_id = $userId, amount = $totalAmount, charge = $charge, profit = $profit, refund = $refundApplied, status = 'successful', medium = 'NIVASITY', payment_channel = 'wallet', transaction_context = 'purchase'
                                    WHERE ref_id = '$refIdSafe'";
                    if (!mysqli_query($conn, $updateTxSql)) {
                        throw new Exception('Failed to update wallet purchase transaction: ' . mysqli_error($conn));
                    }
                } else {
                    $insertTxSql = "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, refund, status, medium, payment_channel, transaction_context)
                                    VALUES ('$refIdSafe', $userId, $totalAmount, $charge, $profit, $refundApplied, 'successful', 'NIVASITY', 'wallet', 'purchase')";
                    if (!mysqli_query($conn, $insertTxSql)) {
                        throw new Exception('Failed to record wallet purchase transaction: ' . mysqli_error($conn));
                    }
                }

                $payableResult = nivasityRecordSchoolPayable($conn, [
                    'school_id' => $schoolId,
                    'source_ref_id' => $refId,
                    'payer_user_id' => $userId,
                    'source_medium' => 'NIVASITY',
                    'source_channel' => $sourceChannel,
                    'item_subtotal' => $sumAmount,
                    'collected_total' => $totalAmount,
                    'charge_amount' => $charge,
                    'refund_amount' => $refundApplied,
                    'metadata' => [
                        'handler' => 'wallet_checkout',
                        'payment_channel' => 'wallet',
                    ],
                ]);

                mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$refIdSafe'");
                mysqli_commit($conn);

                nivasityWalletLog('Processed wallet checkout', [
                    'user_id' => $userId,
                    'ref_id' => $refId,
                    'amount' => $totalAmount,
                    'charge' => $charge,
                    'profit' => $profit,
                    'funding_charge_recovered' => $fundingChargeRecovered,
                    'refund_applied' => $refundApplied,
                    'payable_status' => $payableResult['status'] ?? null,
                ]);

                nivasitySendWalletAlert($conn, $userId, 'debit', [
                    'amount' => $totalAmount,
                    'balance_after' => $balanceAfter,
                    'reference' => $refId,
                    'description' => 'Wallet payment for order ' . $refId,
                ]);

                return [
                    'status' => 'success',
                    'already_processed' => false,
                    'total_amount' => $totalAmount,
                    'charge' => $charge,
                    'profit' => $profit,
                    'funding_charge_recovered' => $fundingChargeRecovered,
                    'refund_applied' => (int)$refundApplied,
                    'manual_ids' => $manualIds,
                    'event_ids' => $eventIds,
                    'wallet_balance_after' => $balanceAfter,
                ];
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                throw $e;
            }
        });
    }
}

if (!function_exists('nivasitySettlementCapPerSchool')) {
    function nivasitySettlementCapPerSchool() {
        return 8000000;
    }
}

if (!function_exists('nivasityGetSchoolSettlementAccount')) {
    function nivasityGetSchoolSettlementAccount($conn, $schoolId) {
        $schoolId = (int)$schoolId;
        if ($schoolId <= 0) {
            return null;
        }

        $sql = "SELECT sa.*, s.name AS school_name
                FROM settlement_accounts sa
                LEFT JOIN schools s ON s.id = sa.school_id
                WHERE sa.school_id = $schoolId
                  AND sa.type = 'school'
                  AND (sa.status = 'active' OR sa.status IS NULL OR sa.status = '')
                ORDER BY sa.id DESC
                LIMIT 1";
        $rs = mysqli_query($conn, $sql);
        if ($rs && mysqli_num_rows($rs) > 0) {
            return mysqli_fetch_assoc($rs);
        }

        return null;
    }
}

if (!function_exists('nivasityGetSettlementProvider')) {
    function nivasityGetSettlementProvider($settlementAccount) {
        $gateway = strtolower(trim((string)($settlementAccount['gateway'] ?? '')));
        if (in_array($gateway, ['paystack', 'flutterwave'], true)) {
            return $gateway;
        }

        if (!empty($settlementAccount['flw_id'])) {
            return 'flutterwave';
        }

        return 'paystack';
    }
}

if (!function_exists('nivasitySettlementRequest')) {
    function nivasitySettlementRequest($url, $method, $payload, $headers = []) {
        $curl = curl_init();
        $encodedPayload = $payload === null ? null : json_encode($payload);
        $mergedHeaders = array_merge(['Content-Type: application/json'], $headers);

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $mergedHeaders,
        ]);

        if ($encodedPayload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedPayload);
        }

        $rawBody = curl_exec($curl);
        $curlError = curl_error($curl);
        $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $decoded = is_string($rawBody) ? json_decode($rawBody, true) : null;

        return [
            'ok' => $curlError === '' && $statusCode >= 200 && $statusCode < 300,
            'status_code' => $statusCode,
            'error' => $curlError,
            'raw_body' => $rawBody,
            'data' => is_array($decoded) ? $decoded : null,
        ];
    }
}

if (!function_exists('nivasityBuildSettlementBatchReference')) {
    function nivasityBuildSettlementBatchReference($schoolId, $scheduledFor) {
        $schoolId = (int)$schoolId;
        $scheduledFor = preg_replace('/[^0-9]/', '', (string)$scheduledFor);
        if ($scheduledFor === '') {
            $scheduledFor = date('Ymd');
        }

        return sprintf('settle_%d_%s_%s', $schoolId, $scheduledFor, substr(str_replace('.', '', (string)microtime(true)), -8));
    }
}

if (!function_exists('nivasityCreatePaystackTransferRecipient')) {
    function nivasityCreatePaystackTransferRecipient($settlementAccount) {
        require_once __DIR__ . '/../config/fw.php';

        if (!defined('PAYSTACK_SECRET_KEY') || PAYSTACK_SECRET_KEY === '') {
            throw new Exception('Paystack secret key is not configured for settlement transfers');
        }

        $payload = [
            'type' => 'nuban',
            'name' => (string)($settlementAccount['acct_name'] ?? ''),
            'account_number' => (string)($settlementAccount['acct_number'] ?? ''),
            'bank_code' => (string)($settlementAccount['bank'] ?? ''),
            'currency' => 'NGN',
        ];

        $response = nivasitySettlementRequest(
            'https://api.paystack.co/transferrecipient',
            'POST',
            $payload,
            ['Authorization: Bearer ' . PAYSTACK_SECRET_KEY]
        );

        if (!$response['ok'] || empty($response['data']['status']) || empty($response['data']['data']['recipient_code'])) {
            $message = $response['error'] !== '' ? $response['error'] : (string)($response['raw_body'] ?? 'Unable to create Paystack transfer recipient');
            throw new Exception('Paystack transfer recipient creation failed: ' . $message);
        }

        return [
            'recipient_code' => (string)$response['data']['data']['recipient_code'],
            'raw_response' => $response['data'],
        ];
    }
}

if (!function_exists('nivasityInitiatePaystackSettlementTransfer')) {
    function nivasityInitiatePaystackSettlementTransfer($settlementAccount, $amount, $batchReference, $narration) {
        require_once __DIR__ . '/../config/fw.php';

        if (!defined('PAYSTACK_SECRET_KEY') || PAYSTACK_SECRET_KEY === '') {
            throw new Exception('Paystack secret key is not configured for settlement transfers');
        }

        $recipient = nivasityCreatePaystackTransferRecipient($settlementAccount);
        $payload = [
            'source' => 'balance',
            'amount' => (int)$amount * 100,
            'recipient' => $recipient['recipient_code'],
            'reason' => (string)$narration,
            'reference' => (string)$batchReference,
        ];

        $response = nivasitySettlementRequest(
            'https://api.paystack.co/transfer',
            'POST',
            $payload,
            ['Authorization: Bearer ' . PAYSTACK_SECRET_KEY]
        );

        if (!$response['ok'] || empty($response['data']['status'])) {
            $message = $response['error'] !== '' ? $response['error'] : (string)($response['raw_body'] ?? 'Unable to create Paystack transfer');
            throw new Exception('Paystack settlement transfer failed: ' . $message);
        }

        $transferData = $response['data']['data'] ?? [];

        return [
            'provider' => 'paystack',
            'provider_reference' => (string)($transferData['reference'] ?? $batchReference),
            'provider_status' => (string)($transferData['status'] ?? 'pending'),
            'raw_response' => $response['data'],
        ];
    }
}

if (!function_exists('nivasityInitiateFlutterwaveSettlementTransfer')) {
    function nivasityInitiateFlutterwaveSettlementTransfer($settlementAccount, $amount, $batchReference, $narration) {
        require_once __DIR__ . '/../config/fw.php';

        if (!defined('FLW_SECRET_KEY') || FLW_SECRET_KEY === '') {
            throw new Exception('Flutterwave secret key is not configured for settlement transfers');
        }

        $payload = [
            'account_bank' => (string)($settlementAccount['bank'] ?? ''),
            'account_number' => (string)($settlementAccount['acct_number'] ?? ''),
            'amount' => (int)$amount,
            'currency' => 'NGN',
            'narration' => (string)$narration,
            'reference' => (string)$batchReference,
            'debit_currency' => 'NGN',
        ];

        $response = nivasitySettlementRequest(
            'https://api.flutterwave.com/v3/transfers',
            'POST',
            $payload,
            ['Authorization: Bearer ' . FLW_SECRET_KEY]
        );

        if (!$response['ok'] || strtolower((string)($response['data']['status'] ?? '')) !== 'success') {
            $message = $response['error'] !== '' ? $response['error'] : (string)($response['raw_body'] ?? 'Unable to create Flutterwave transfer');
            throw new Exception('Flutterwave settlement transfer failed: ' . $message);
        }

        $transferData = $response['data']['data'] ?? [];

        return [
            'provider' => 'flutterwave',
            'provider_reference' => (string)($transferData['reference'] ?? $batchReference),
            'provider_status' => (string)($transferData['status'] ?? 'pending'),
            'raw_response' => $response['data'],
        ];
    }
}

if (!function_exists('nivasityInitiateSchoolSettlementTransfer')) {
    function nivasityInitiateSchoolSettlementTransfer($settlementAccount, $amount, $batchReference, $dryRun = false) {
        $amount = (int)$amount;
        if ($amount <= 0) {
            throw new Exception('Settlement transfer amount must be greater than zero');
        }

        $provider = nivasityGetSettlementProvider($settlementAccount);
        $schoolName = trim((string)($settlementAccount['school_name'] ?? 'school'));
        $narration = sprintf('Nivasity school settlement for %s', $schoolName);

        if ($dryRun) {
            return [
                'provider' => $provider,
                'provider_reference' => $batchReference,
                'provider_status' => 'dry_run',
                'raw_response' => [
                    'status' => true,
                    'message' => 'Dry run only',
                ],
            ];
        }

        if ($provider === 'flutterwave') {
            return nivasityInitiateFlutterwaveSettlementTransfer($settlementAccount, $amount, $batchReference, $narration);
        }

        return nivasityInitiatePaystackSettlementTransfer($settlementAccount, $amount, $batchReference, $narration);
    }
}

if (!function_exists('nivasityStageSchoolSettlementBatch')) {
    function nivasityStageSchoolSettlementBatch($conn, $schoolId, $scheduledFor, $dryRun = false) {
        $schoolId = (int)$schoolId;
        $scheduledFor = trim((string)$scheduledFor);
        if ($schoolId <= 0) {
            return ['status' => 'ignored', 'message' => 'Invalid school'];
        }
        if ($scheduledFor === '') {
            $scheduledFor = date('Y-m-d');
        }

        mysqli_begin_transaction($conn);
        try {
            $walletSql = "SELECT siw.*, s.name AS school_name
                          FROM school_internal_wallets siw
                          LEFT JOIN schools s ON s.id = siw.school_id
                          WHERE siw.school_id = $schoolId
                          LIMIT 1 FOR UPDATE";
            $walletRs = mysqli_query($conn, $walletSql);
            if (!$walletRs || mysqli_num_rows($walletRs) < 1) {
                mysqli_commit($conn);
                return ['status' => 'no_wallet', 'message' => 'School internal wallet not found'];
            }

            $walletRow = mysqli_fetch_assoc($walletRs);
            $pendingBalance = (int)($walletRow['pending_payout_balance'] ?? 0);
            $schoolName = (string)($walletRow['school_name'] ?? ('School #' . $schoolId));
            if ((string)($walletRow['status'] ?? 'active') !== 'active' || $pendingBalance <= 0) {
                mysqli_commit($conn);
                return [
                    'status' => 'no_funds',
                    'school_id' => $schoolId,
                    'school_name' => $schoolName,
                    'message' => 'No pending settlement balance',
                ];
            }

            $processingSql = "SELECT id, batch_reference FROM settlement_batches WHERE school_id = $schoolId AND status = 'processing' ORDER BY id DESC LIMIT 1";
            $processingRs = mysqli_query($conn, $processingSql);
            if ($processingRs && mysqli_num_rows($processingRs) > 0) {
                $processingRow = mysqli_fetch_assoc($processingRs);
                mysqli_commit($conn);
                return [
                    'status' => 'processing',
                    'school_id' => $schoolId,
                    'school_name' => $schoolName,
                    'batch_id' => (int)($processingRow['id'] ?? 0),
                    'batch_reference' => $processingRow['batch_reference'] ?? null,
                    'message' => 'School already has a processing settlement batch',
                ];
            }

            $settlementAccount = nivasityGetSchoolSettlementAccount($conn, $schoolId);
            if (!$settlementAccount) {
                mysqli_commit($conn);
                return [
                    'status' => 'missing_account',
                    'school_id' => $schoolId,
                    'school_name' => $schoolName,
                    'message' => 'School settlement account not found',
                ];
            }

            $capAmount = nivasitySettlementCapPerSchool();
            $targetAmount = min($pendingBalance, $capAmount);
            $ledgerSql = "SELECT id, source_ref_id, payable_amount, settled_amount, created_at
                          FROM school_payable_ledger
                          WHERE school_id = $schoolId
                            AND payable_amount > settled_amount
                          ORDER BY created_at ASC, id ASC
                          FOR UPDATE";
            $ledgerRs = mysqli_query($conn, $ledgerSql);
            if (!$ledgerRs) {
                throw new Exception('Failed to load school payable ledger for settlement staging: ' . mysqli_error($conn));
            }

            $allocatedItems = [];
            $allocatedAmount = 0;
            while ($ledgerRow = mysqli_fetch_assoc($ledgerRs)) {
                $outstanding = max(0, (int)$ledgerRow['payable_amount'] - (int)$ledgerRow['settled_amount']);
                if ($outstanding <= 0) {
                    continue;
                }
                $remaining = $targetAmount - $allocatedAmount;
                if ($remaining <= 0) {
                    break;
                }
                $allocation = min($outstanding, $remaining);
                $allocatedItems[] = [
                    'ledger_id' => (int)$ledgerRow['id'],
                    'source_ref_id' => (string)$ledgerRow['source_ref_id'],
                    'allocated_amount' => (int)$allocation,
                ];
                $allocatedAmount += $allocation;
            }

            if ($allocatedAmount <= 0 || empty($allocatedItems)) {
                mysqli_commit($conn);
                return [
                    'status' => 'no_eligible_records',
                    'school_id' => $schoolId,
                    'school_name' => $schoolName,
                    'message' => 'No payable records eligible for settlement',
                ];
            }

            $provider = nivasityGetSettlementProvider($settlementAccount);
            if ($dryRun) {
                mysqli_commit($conn);
                return [
                    'status' => 'dry_run',
                    'school_id' => $schoolId,
                    'school_name' => $schoolName,
                    'provider' => $provider,
                    'total_amount' => $allocatedAmount,
                    'total_records' => count($allocatedItems),
                    'batch_reference' => nivasityBuildSettlementBatchReference($schoolId, $scheduledFor),
                    'allocations' => $allocatedItems,
                ];
            }

            $batchReference = nivasityBuildSettlementBatchReference($schoolId, $scheduledFor);
            $batchReferenceSafe = mysqli_real_escape_string($conn, $batchReference);
            $providerSafe = mysqli_real_escape_string($conn, $provider);
            $notesSafe = mysqli_real_escape_string($conn, 'Cron-triggered school settlement');
            $insertBatchSql = "INSERT INTO settlement_batches (
                    school_id, scheduled_for, batch_reference, status, total_amount, total_records,
                    transfer_provider, started_at, notes
                ) VALUES (
                    $schoolId, '$scheduledFor', '$batchReferenceSafe', 'processing', $allocatedAmount, " . count($allocatedItems) . ",
                    '$providerSafe', NOW(), '$notesSafe'
                )";
            if (!mysqli_query($conn, $insertBatchSql)) {
                throw new Exception('Failed to create settlement batch: ' . mysqli_error($conn));
            }
            $batchId = (int)mysqli_insert_id($conn);

            foreach ($allocatedItems as $allocation) {
                $sourceRefSafe = mysqli_real_escape_string($conn, $allocation['source_ref_id']);
                $ledgerId = (int)$allocation['ledger_id'];
                $allocationAmount = (int)$allocation['allocated_amount'];
                $insertItemSql = "INSERT INTO settlement_batch_items (
                        settlement_batch_id, school_payable_ledger_id, source_ref_id, allocated_amount, status
                    ) VALUES (
                        $batchId, $ledgerId, '$sourceRefSafe', $allocationAmount, 'pending'
                    )";
                if (!mysqli_query($conn, $insertItemSql)) {
                    throw new Exception('Failed to create settlement batch item: ' . mysqli_error($conn));
                }
            }

            mysqli_commit($conn);

            return [
                'status' => 'staged',
                'batch_id' => $batchId,
                'batch_reference' => $batchReference,
                'school_id' => $schoolId,
                'school_name' => $schoolName,
                'provider' => $provider,
                'total_amount' => $allocatedAmount,
                'total_records' => count($allocatedItems),
                'allocations' => $allocatedItems,
                'settlement_account' => $settlementAccount,
            ];
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }
    }
}

if (!function_exists('nivasityCompleteSchoolSettlementBatch')) {
    function nivasityCompleteSchoolSettlementBatch($conn, $batchId, $providerReference, $providerResponse = []) {
        $batchId = (int)$batchId;
        if ($batchId <= 0) {
            throw new Exception('Invalid settlement batch id');
        }

        mysqli_begin_transaction($conn);
        try {
            $batchSql = "SELECT * FROM settlement_batches WHERE id = $batchId LIMIT 1 FOR UPDATE";
            $batchRs = mysqli_query($conn, $batchSql);
            if (!$batchRs || mysqli_num_rows($batchRs) < 1) {
                throw new Exception('Settlement batch not found for completion');
            }
            $batchRow = mysqli_fetch_assoc($batchRs);
            $schoolId = (int)($batchRow['school_id'] ?? 0);

            $itemsSql = "SELECT bi.id AS item_id, bi.allocated_amount, l.id AS ledger_id, l.payable_amount, l.settled_amount
                         FROM settlement_batch_items bi
                         INNER JOIN school_payable_ledger l ON l.id = bi.school_payable_ledger_id
                         WHERE bi.settlement_batch_id = $batchId
                         ORDER BY bi.id ASC
                         FOR UPDATE";
            $itemsRs = mysqli_query($conn, $itemsSql);
            if (!$itemsRs) {
                throw new Exception('Failed to load settlement batch items: ' . mysqli_error($conn));
            }

            $appliedTotal = 0;
            while ($itemRow = mysqli_fetch_assoc($itemsRs)) {
                $ledgerId = (int)$itemRow['ledger_id'];
                $allocatedAmount = (int)$itemRow['allocated_amount'];
                $payableAmount = (int)$itemRow['payable_amount'];
                $existingSettled = (int)$itemRow['settled_amount'];
                $newSettled = $existingSettled + $allocatedAmount;
                $newStatus = $newSettled >= $payableAmount ? 'settled' : 'partially_settled';

                $updateLedgerSql = "UPDATE school_payable_ledger
                                    SET settled_amount = $newSettled,
                                        status = '$newStatus',
                                        updated_at = NOW()
                                    WHERE id = $ledgerId";
                if (!mysqli_query($conn, $updateLedgerSql)) {
                    throw new Exception('Failed to update school payable ledger settlement state: ' . mysqli_error($conn));
                }

                $itemId = (int)$itemRow['item_id'];
                $updateItemSql = "UPDATE settlement_batch_items SET status = 'settled', updated_at = NOW() WHERE id = $itemId";
                if (!mysqli_query($conn, $updateItemSql)) {
                    throw new Exception('Failed to update settlement batch item state: ' . mysqli_error($conn));
                }

                $appliedTotal += $allocatedAmount;
            }

            $walletSql = "SELECT current_balance, pending_payout_balance FROM school_internal_wallets WHERE school_id = $schoolId LIMIT 1 FOR UPDATE";
            $walletRs = mysqli_query($conn, $walletSql);
            if (!$walletRs || mysqli_num_rows($walletRs) < 1) {
                throw new Exception('School internal wallet missing while completing settlement');
            }
            $walletRow = mysqli_fetch_assoc($walletRs);
            $newCurrentBalance = max(0, (int)$walletRow['current_balance'] - $appliedTotal);
            $newPendingBalance = max(0, (int)$walletRow['pending_payout_balance'] - $appliedTotal);
            $updateWalletSql = "UPDATE school_internal_wallets
                                SET current_balance = $newCurrentBalance,
                                    pending_payout_balance = $newPendingBalance,
                                    updated_at = NOW()
                                WHERE school_id = $schoolId";
            if (!mysqli_query($conn, $updateWalletSql)) {
                throw new Exception('Failed to update school internal wallet after settlement: ' . mysqli_error($conn));
            }

            $providerReferenceSafe = mysqli_real_escape_string($conn, (string)$providerReference);
            $providerResponseSafe = mysqli_real_escape_string($conn, json_encode($providerResponse));
            $updateBatchSql = "UPDATE settlement_batches
                               SET status = 'completed',
                                   total_amount = $appliedTotal,
                                   provider_reference = '$providerReferenceSafe',
                                   provider_response = '$providerResponseSafe',
                                   completed_at = NOW(),
                                   updated_at = NOW()
                               WHERE id = $batchId";
            if (!mysqli_query($conn, $updateBatchSql)) {
                throw new Exception('Failed to complete settlement batch: ' . mysqli_error($conn));
            }

            mysqli_commit($conn);

            nivasityWalletLog('Completed school settlement batch', [
                'batch_id' => $batchId,
                'school_id' => $schoolId,
                'amount' => $appliedTotal,
                'provider_reference' => $providerReference,
            ]);

            return [
                'status' => 'completed',
                'batch_id' => $batchId,
                'school_id' => $schoolId,
                'amount' => $appliedTotal,
            ];
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }
    }
}

if (!function_exists('nivasityFailSchoolSettlementBatch')) {
    function nivasityFailSchoolSettlementBatch($conn, $batchId, $errorMessage, $providerResponse = []) {
        $batchId = (int)$batchId;
        if ($batchId <= 0) {
            return;
        }

        mysqli_begin_transaction($conn);
        try {
            $errorSafe = mysqli_real_escape_string($conn, trim((string)$errorMessage));
            $providerResponseSafe = mysqli_real_escape_string($conn, json_encode($providerResponse));
            $updateBatchSql = "UPDATE settlement_batches
                               SET status = 'failed',
                                   provider_response = '$providerResponseSafe',
                                   last_error = '$errorSafe',
                                   failed_at = NOW(),
                                   updated_at = NOW()
                               WHERE id = $batchId";
            if (!mysqli_query($conn, $updateBatchSql)) {
                throw new Exception('Failed to mark settlement batch as failed: ' . mysqli_error($conn));
            }

            $updateItemsSql = "UPDATE settlement_batch_items SET status = 'failed', notes = '$errorSafe', updated_at = NOW() WHERE settlement_batch_id = $batchId";
            if (!mysqli_query($conn, $updateItemsSql)) {
                throw new Exception('Failed to mark settlement batch items as failed: ' . mysqli_error($conn));
            }

            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }
    }
}

if (!function_exists('nivasityRunSettlementSweep')) {
    function nivasityRunSettlementSweep($conn, $options = []) {
        $schoolId = (int)($options['school_id'] ?? 0);
        $limit = (int)($options['limit'] ?? 0);
        $dryRun = !empty($options['dry_run']);
        $force = !empty($options['force']);
        $scheduledFor = trim((string)($options['scheduled_for'] ?? date('Y-m-d')));

        if ($scheduledFor === '') {
            $scheduledFor = date('Y-m-d');
        }

        $dayOfWeek = (int)date('N', strtotime($scheduledFor));
        if (!$force && $dayOfWeek !== 5) {
            return [
                'status' => 'skipped',
                'message' => 'Settlement sweep only runs on Friday unless force=1 is supplied.',
                'scheduled_for' => $scheduledFor,
                'day_of_week' => $dayOfWeek,
                'cap_per_school' => nivasitySettlementCapPerSchool(),
                'dry_run' => $dryRun ? 1 : 0,
                'results' => [],
            ];
        }

        $where = ["siw.status = 'active'", 'siw.pending_payout_balance > 0'];
        if ($schoolId > 0) {
            $where[] = 'siw.school_id = ' . $schoolId;
        }
        $whereSql = implode(' AND ', $where);
        $limitSql = $limit > 0 ? ' LIMIT ' . $limit : '';
        $schoolsSql = "SELECT siw.school_id, siw.pending_payout_balance, s.name AS school_name
                       FROM school_internal_wallets siw
                       LEFT JOIN schools s ON s.id = siw.school_id
                       WHERE $whereSql
                       ORDER BY siw.pending_payout_balance DESC, siw.school_id ASC" . $limitSql;
        $schoolsRs = mysqli_query($conn, $schoolsSql);
        if (!$schoolsRs) {
            throw new Exception('Failed to load schools for settlement sweep: ' . mysqli_error($conn));
        }

        $summary = [
            'status' => 'success',
            'scheduled_for' => $scheduledFor,
            'cap_per_school' => nivasitySettlementCapPerSchool(),
            'dry_run' => $dryRun ? 1 : 0,
            'schools_scanned' => 0,
            'batches_completed' => 0,
            'batches_failed' => 0,
            'total_settled' => 0,
            'results' => [],
        ];

        while ($schoolRow = mysqli_fetch_assoc($schoolsRs)) {
            $summary['schools_scanned']++;
            $currentSchoolId = (int)($schoolRow['school_id'] ?? 0);
            $schoolName = (string)($schoolRow['school_name'] ?? ('School #' . $currentSchoolId));
            $stageResult = [];

            try {
                $stageResult = nivasityStageSchoolSettlementBatch($conn, $currentSchoolId, $scheduledFor, $dryRun);
                $stageResult['school_name'] = $stageResult['school_name'] ?? $schoolName;

                if (($stageResult['status'] ?? '') === 'staged') {
                    $transferResult = nivasityInitiateSchoolSettlementTransfer(
                        $stageResult['settlement_account'],
                        (int)$stageResult['total_amount'],
                        (string)$stageResult['batch_reference'],
                        false
                    );
                    $completion = nivasityCompleteSchoolSettlementBatch(
                        $conn,
                        (int)$stageResult['batch_id'],
                        (string)$transferResult['provider_reference'],
                        $transferResult['raw_response'] ?? []
                    );

                    $summary['batches_completed']++;
                    $summary['total_settled'] += (int)($completion['amount'] ?? 0);
                    $summary['results'][] = array_merge($stageResult, [
                        'status' => 'completed',
                        'provider_reference' => $transferResult['provider_reference'] ?? null,
                        'provider_status' => $transferResult['provider_status'] ?? null,
                    ]);
                    continue;
                }

                $summary['results'][] = $stageResult;
            } catch (Throwable $e) {
                if (!empty($stageResult['batch_id'])) {
                    try {
                        nivasityFailSchoolSettlementBatch($conn, (int)$stageResult['batch_id'], $e->getMessage());
                    } catch (Throwable $inner) {
                        nivasityWalletLog('Failed to mark settlement batch as failed', [
                            'batch_id' => (int)$stageResult['batch_id'],
                            'error' => $inner->getMessage(),
                        ]);
                    }
                }

                $summary['batches_failed']++;
                $summary['results'][] = [
                    'status' => 'failed',
                    'school_id' => $currentSchoolId,
                    'school_name' => $schoolName,
                    'batch_id' => $stageResult['batch_id'] ?? null,
                    'batch_reference' => $stageResult['batch_reference'] ?? null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }
}
