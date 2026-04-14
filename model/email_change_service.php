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
            "SELECT id FROM users WHERE id != " . (int) $userId . " AND LOWER(TRIM(email)) = '$newEmailSafe' LIMIT 1"
        );

        if ($duplicateResult && mysqli_num_rows($duplicateResult) > 0) {
            throw new Exception('That email address is already in use by another account.');
        }

        return [
            'user' => $user,
            'new_email' => $normalizedNewEmail,
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