<?php

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/functions.php';

if (!function_exists('nivasityEmailChangeRequestsTableExists')) {
    function nivasityEmailChangeRequestsTableExists($conn) {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        $result = mysqli_query($conn, "SHOW TABLES LIKE 'email_change_requests'");
        $exists = $result && mysqli_num_rows($result) > 0;

        return $exists;
    }
}

if (!function_exists('nivasityGetEmailChangeUserById')) {
    function nivasityGetEmailChangeUserById($conn, $userId) {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return null;
        }

        $result = mysqli_query($conn, "SELECT id, first_name, last_name, email FROM users WHERE id = $userId LIMIT 1");
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }

        return null;
    }
}

if (!function_exists('nivasityNormalizeEmailAddress')) {
    function nivasityNormalizeEmailAddress($email) {
        return strtolower(trim((string) $email));
    }
}

if (!function_exists('nivasityEmailHolderIsEmptyAccount')) {
    // True when an account has nothing worth keeping: no purchases, payments, wallet money,
    // transfers or pending bulk claims. Used to let a student take back their email from an
    // unused duplicate account (they prove they own the email with the OTP).
    function nivasityEmailHolderIsEmptyAccount($conn, $holderId) {
        $holderId = (int) $holderId;
        $holder = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id = $holderId LIMIT 1"));
        if (!$holder || !in_array((string) $holder['role'], ['student', 'hoc'], true)) {
            return false;
        }

        $checks = [
            "SELECT 1 FROM manuals_bought WHERE buyer = $holderId LIMIT 1",
            "SELECT 1 FROM transactions WHERE user_id = $holderId LIMIT 1",
            "SELECT 1 FROM user_wallets WHERE user_id = $holderId AND balance <> 0 LIMIT 1",
        ];
        $optional = [
            'wallet_transfers' => "SELECT 1 FROM wallet_transfers WHERE sender_user_id = $holderId OR recipient_user_id = $holderId LIMIT 1",
            'manual_bulk_payment_students' => "SELECT 1 FROM manual_bulk_payment_students WHERE (matched_user_id = $holderId OR placeholder_user_id = $holderId) AND claimed_at IS NULL LIMIT 1",
        ];
        foreach ($optional as $table => $sql) {
            $exists = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
            if ($exists && mysqli_num_rows($exists) > 0) {
                $checks[] = $sql;
            }
        }

        foreach ($checks as $sql) {
            $rs = mysqli_query($conn, $sql);
            if (!$rs || mysqli_num_rows($rs) > 0) {
                return false; // query failure counts as "not empty": never retire on doubt
            }
        }
        return true;
    }
}

if (!function_exists('nivasityEnsureEmailCanBeChanged')) {
    function nivasityEnsureEmailCanBeChanged($conn, $userId, $newEmail) {
        $user = nivasityGetEmailChangeUserById($conn, $userId);
        if (!$user) {
            throw new Exception('User account was not found.');
        }

        $normalizedCurrentEmail = nivasityNormalizeEmailAddress($user['email'] ?? '');
        $normalizedNewEmail = nivasityNormalizeEmailAddress($newEmail);

        if ($normalizedNewEmail === '') {
            throw new Exception('A new email address is required.');
        }

        if (!filter_var($normalizedNewEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Enter a valid email address.');
        }

        if ($normalizedNewEmail === $normalizedCurrentEmail) {
            throw new Exception('This is already your current email address.');
        }

        $newEmailSafe = mysqli_real_escape_string($conn, $normalizedNewEmail);
        $duplicateResult = mysqli_query(
            $conn,
            "SELECT id FROM users WHERE id != " . (int) $userId . " AND LOWER(TRIM(email)) = '$newEmailSafe'"
        );

        // Another account uses this email. If it is an unused duplicate (nothing bought, no wallet
        // money), it is retired when the OTP sent to that email is confirmed. Otherwise support must merge.
        $retireUserIds = [];
        if ($duplicateResult) {
            while ($holder = mysqli_fetch_assoc($duplicateResult)) {
                if (!nivasityEmailHolderIsEmptyAccount($conn, (int) $holder['id'])) {
                    throw new Exception('That email address is used by another account that has purchases or wallet funds. Contact support to merge the two accounts.');
                }
                $retireUserIds[] = (int) $holder['id'];
            }
        }

        return [
            'user' => $user,
            'new_email' => $normalizedNewEmail,
            'retire_user_ids' => $retireUserIds,
        ];
    }
}

if (!function_exists('nivasityDispatchEmailChangeOtp')) {
    function nivasityDispatchEmailChangeOtp($conn, $userId, $newEmail) {
        if (!nivasityEmailChangeRequestsTableExists($conn)) {
            throw new Exception('Email change requests are not available until the latest SQL update is applied.');
        }

        $validated = nivasityEnsureEmailCanBeChanged($conn, $userId, $newEmail);
        $user = $validated['user'];
        $normalizedNewEmail = $validated['new_email'];
        $otp = (string) rand(100000, 999999);
        $expDate = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $newEmailSafe = mysqli_real_escape_string($conn, $normalizedNewEmail);
        $otpSafe = mysqli_real_escape_string($conn, $otp);
        $expDateSafe = mysqli_real_escape_string($conn, $expDate);
        $userId = (int) $userId;

        mysqli_query($conn, "DELETE FROM email_change_requests WHERE user_id = $userId");

        $insertSql = "INSERT INTO email_change_requests (user_id, new_email, otp, exp_date) VALUES ($userId, '$newEmailSafe', '$otpSafe', '$expDateSafe')";
        if (!mysqli_query($conn, $insertSql)) {
            throw new Exception('Failed to generate email verification code. Please try again.');
        }

        $firstName = trim((string) ($user['first_name'] ?? ''));
        if ($firstName === '') {
            $firstName = 'there';
        }

        $subject = 'Confirm Your New Email Address - NIVASITY';
        $body = "Hello {$firstName},
<br><br>
Use the verification code below to confirm your new email address on Nivasity:
<br><br>
<strong style='font-size: 24px; letter-spacing: 2px;'>{$otp}</strong>
<br><br>
This code will expire in 10 minutes.
<br><br>
If you did not request this change, please ignore this email.
<br><br>
Best regards,<br><b>Nivasity Team</b>";

        $mailStatus = function_exists('sendBrevoMail') ? sendBrevoMail($subject, $body, $normalizedNewEmail) : sendMail($subject, $body, $normalizedNewEmail);
        if ($mailStatus !== 'success' && function_exists('sendMail')) {
            $mailStatus = sendMail($subject, $body, $normalizedNewEmail);
        }

        if ($mailStatus !== 'success') {
            throw new Exception('Failed to send verification code to the new email address.');
        }

        return [
            'new_email' => $normalizedNewEmail,
            'expires_in' => 600,
        ];
    }
}

if (!function_exists('nivasityConfirmEmailChangeOtp')) {
    function nivasityConfirmEmailChangeOtp($conn, $userId, $newEmail, $otp) {
        if (!nivasityEmailChangeRequestsTableExists($conn)) {
            throw new Exception('Email change requests are not available until the latest SQL update is applied.');
        }

        $validated = nivasityEnsureEmailCanBeChanged($conn, $userId, $newEmail);
        $user = $validated['user'];
        $normalizedNewEmail = $validated['new_email'];
        $otp = trim((string) $otp);

        if (!preg_match('/^\d{6}$/', $otp)) {
            throw new Exception('Enter the 6-digit OTP sent to your new email address.');
        }

        $userId = (int) $userId;
        $newEmailSafe = mysqli_real_escape_string($conn, $normalizedNewEmail);
        $otpSafe = mysqli_real_escape_string($conn, $otp);
        $nowSafe = mysqli_real_escape_string($conn, date('Y-m-d H:i:s'));

        $requestResult = mysqli_query(
            $conn,
            "SELECT id FROM email_change_requests WHERE user_id = $userId AND new_email = '$newEmailSafe' AND otp = '$otpSafe' AND exp_date >= '$nowSafe' LIMIT 1"
        );

        if (!$requestResult || mysqli_num_rows($requestResult) < 1) {
            throw new Exception('Invalid or expired OTP. Please request a new code.');
        }

        // Retire unused duplicates that held this email: free the email and matric number, block sign-in.
        foreach ($validated['retire_user_ids'] ?? [] as $retireId) {
            $retireId = (int) $retireId;
            if (!nivasityEmailHolderIsEmptyAccount($conn, $retireId)) {
                throw new Exception('That email address is used by another account that has purchases or wallet funds. Contact support to merge the two accounts.');
            }
            $retiredEmail = 'retired+' . $retireId . '@nivasity.invalid';
            if (!mysqli_query($conn, "UPDATE users SET email = '$retiredEmail', matric_no = NULL, status = 'deactivated' WHERE id = $retireId LIMIT 1")) {
                throw new Exception('Failed to update your email address. Please try again later.');
            }
            error_log('[EMAIL_CHANGE] user ' . $userId . ' took ' . $normalizedNewEmail . ' from empty duplicate account ' . $retireId);
        }

        $updateSql = "UPDATE users SET email = '$newEmailSafe' WHERE id = $userId LIMIT 1";
        if (!mysqli_query($conn, $updateSql)) {
            throw new Exception('Failed to update your email address. Please try again later.');
        }

        mysqli_query($conn, "DELETE FROM email_change_requests WHERE user_id = $userId");

        return [
            'old_email' => (string) ($user['email'] ?? ''),
            'email' => $normalizedNewEmail,
        ];
    }
}