<?php
session_start();
require_once __DIR__ . '/../model/config.php';
require_once __DIR__ . '/../model/mail.php';
require_once __DIR__ . '/../model/functions.php';

$allowedEmails = [
  'akinyemisamuel170@gmail.com',
  'samuel@nivasity.com',
  'blessing.cf@nivasity.com'
];

if (!isset($_SESSION['nivas_userId'])) {
  header('Location: ../signin.html');
  exit();
}

$currentUserId = (int) $_SESSION['nivas_userId'];
$currentUserQuery = mysqli_query($conn, "SELECT email, first_name FROM users WHERE id = $currentUserId LIMIT 1");

if (!$currentUserQuery || mysqli_num_rows($currentUserQuery) === 0) {
  http_response_code(403);
  echo '<h1>Access denied</h1><p>We could not verify your access level.</p>';
  exit();
}

$currentUser = mysqli_fetch_assoc($currentUserQuery);
$currentEmail = strtolower($currentUser['email']);

if (!in_array($currentEmail, $allowedEmails, true)) {
  http_response_code(403);
  echo '<h1>Access denied</h1><p>This page is restricted to authorised accounts.</p>';
  exit();
}

// Get all unverified users created in the past 14 days
// Using last_login as a proxy for creation date since unverified users haven't logged in yet
// The last_login field defaults to current_timestamp() on row creation
$pendingUsersQuery = mysqli_query($conn, 
  "SELECT id, first_name, email, role 
   FROM users 
   WHERE status = 'unverified' 
   AND last_login >= DATE_SUB(NOW(), INTERVAL 14 DAY)
   ORDER BY last_login DESC"
);

if (!$pendingUsersQuery) {
  http_response_code(500);
  echo '<h1>Error</h1><p>Unable to retrieve pending verifications at this time.</p>';
  exit();
}

$results = [];
$successCount = 0;
$totalCount = 0;

while ($pendingUser = mysqli_fetch_assoc($pendingUsersQuery)) {
  $totalCount++;
  $pendingUserId = (int) $pendingUser['id'];
  $verificationCode = generateVerificationCode(12);

  while (!isCodeUnique($verificationCode, $conn, 'verification_code')) {
    $verificationCode = generateVerificationCode(12);
  }

  $escapedCode = mysqli_real_escape_string($conn, $verificationCode);

  $existingCodeQuery = mysqli_query($conn, "SELECT user_id FROM verification_code WHERE user_id = $pendingUserId LIMIT 1");

  if ($existingCodeQuery && mysqli_num_rows($existingCodeQuery) > 0) {
    $codeQuery = mysqli_query($conn, "UPDATE verification_code SET code = '$escapedCode', exp_date = NULL WHERE user_id = $pendingUserId");
  } else {
    $codeQuery = mysqli_query($conn, "INSERT INTO verification_code (user_id, code) VALUES ($pendingUserId, '$escapedCode')");
  }

  if (!$codeQuery) {
    $results[] = [
      'email' => $pendingUser['email'],
      'status' => 'error',
      'message' => 'Could not update verification code.'
    ];
    continue;
  }

  switch ($pendingUser['role']) {
    case 'org_admin':
      $verificationPath = "setup_org.html?verify=$verificationCode";
      break;
    case 'visitor':
      $verificationPath = "verify.html?verify=$verificationCode";
      break;
    default:
      $verificationPath = "setup.html?verify=$verificationCode";
  }

  $subject = 'Verify Your Account on NIVASITY';
  $firstName = $pendingUser['first_name'];
  $body = "Hello $firstName,<br><br>"
    . "We're sending you a new verification link so you can finish setting up your Nivasity account.<br><br>"
    . "Click on the following link to verify your account: <a href='https://funaab.nivasity.com/$verificationPath'>Verify Account</a><br>"
    . "If you are unable to click on the link, please copy and paste the following URL into your browser: https://funaab.nivasity.com/$verificationPath<br><br>"
    . "Thank you for choosing Nivasity. We look forward to serving you!<br><br>"
    . 'Best regards,<br><b>Nivasity Team</b>';

  $mailStatus = sendBrevoMail($subject, $body, $pendingUser['email']);

  if ($mailStatus === 'success') {
    $successCount++;
  }

  $results[] = [
    'email' => $pendingUser['email'],
    'status' => $mailStatus,
    'message' => $mailStatus === 'success' ? 'Verification email sent.' : 'Failed to send verification email.'
  ];
}

mysqli_free_result($pendingUsersQuery);

?><!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resend Verification Links (Past 14 Days)</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 40px;
      color: #333;
    }

    h1 {
      color: #7a3b73;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }

    th,
    td {
      border: 1px solid #ddd;
      padding: 12px;
      text-align: left;
    }

    th {
      background-color: #f7f0f7;
    }

    tr:nth-child(even) {
      background-color: #faf5fa;
    }

    .status-success {
      color: #2e7d32;
      font-weight: bold;
    }

    .status-error {
      color: #c62828;
      font-weight: bold;
    }

    .summary {
      margin-top: 10px;
      font-size: 1.1rem;
    }
  </style>
</head>

<body>
  <h1>Verification Resend Summary (Past 14 Days)</h1>
  <p class="summary">
    <?php if ($totalCount === 0): ?>
      No users registered in the past 14 days are currently waiting for verification.
    <?php else: ?>
      Successfully sent <?php echo $successCount; ?> of <?php echo $totalCount; ?> verification emails to users registered in the past 14 days.
    <?php endif; ?>
  </p>
  <?php if (!empty($results)): ?>
    <table>
      <thead>
        <tr>
          <th>Email</th>
          <th>Status</th>
          <th>Message</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results as $result): ?>
          <tr>
            <td><?php echo htmlspecialchars($result['email'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="status-<?php echo $result['status'] === 'success' ? 'success' : 'error'; ?>">
              <?php echo htmlspecialchars(ucfirst($result['status']), ENT_QUOTES, 'UTF-8'); ?>
            </td>
            <td><?php echo htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</body>

</html>
