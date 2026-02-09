<?php
require_once __DIR__ . '/../config/mail.php';

//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;

function buildEmailTemplate($body)
{
  $body = str_replace("\r\n", '<br>', $body);

  return <<<HTML
  <html>
  <head>
      <style>
          /* Import Nunito font for supported clients */
          @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap");

          body {
              font-family: "Nunito", Arial, sans-serif;
              background-color: #fff7ec;
              color: #333;
              padding: 0;
              margin: 0;
          }

          .container {
              width: 100%;
              max-width: 600px;
              margin: 40px auto;
              box-sizing: border-box;
              background-color: #fff;
              padding: 20px;
              border: 2px solid #7a3b73;
              border-radius: 8px;
          }

          .header {
              padding-left: 10px;
              padding-top: 10px;
          }

          .header img {
              height: 50px;
          }

          .content {
              /* border-top: solid px #FF9100;
                border-bottom: solid px #FF9100; */
              padding: 20px;
              font-size: 16px;
              line-height: 1.6;
          }

          .content p {
              margin: 0 0 10px;
          }

          .content ol {
            font-weight: bold;
            color: #7a3b73;
          }

          .btn {
              display: inline-block;
              background-color: #FF9100;
              color: #fff !important;
              padding: 10px 20px;
              text-decoration: none;
              border-radius: 5px;
              font-weight: bold;
              text-align: center;
          }

          a {
              color: #FF9100;
          }

          .footer {
              max-width: 600px;
              margin: 0 auto;
              box-sizing: border-box;
              font-size: 15px;
              color: #555555;
              text-align: center;
          }
      </style>
  </head>

  <body>
      <div class="container">
          <div class="header">
              <img src="https://funaab.nivasity.com/assets/images/nivasity-main.png" alt="Nivasty">
          </div>
          <div class="content">
              {$body}
          </div>
      </div>
      <div class="footer">
          <p>For any feedback or inquiries, get in touch with us at<br>
              <a href="mailto:support@nivasity.com">support@nivasity.com</a> <br> <br>

              Nivasity's services are provided by Nivasity Web Services.<br>
              A business duly incorporated under the laws of Nigeria. <br> <br><br>

              Copyright © Nivasity. 2025 All rights reserved.<br>
      </div>
  </body>

  </html>
HTML;
}

/**
 * Check Brevo account credits
 * Returns the subscription credits or false on error
 * Uses caching to avoid excessive API calls (cache expires after 5 minutes)
 */
function checkBrevoCredits()
{
  static $cachedCredits = null;
  static $cacheTime = null;
  
  // Check if cache is valid (5 minutes)
  if ($cachedCredits !== null && $cacheTime !== null && (time() - $cacheTime) < 300) {
    return $cachedCredits;
  }
  
  if (!defined('BREVO_API_KEY') || !BREVO_API_KEY) {
    error_log('Brevo API key not configured for credit check');
    return false;
  }
  
  $ch = curl_init('https://api.brevo.com/v3/account');
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'api-key: ' . BREVO_API_KEY,
  ]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  
  $response = curl_exec($ch);
  
  if ($response === false) {
    error_log('Brevo credit check error: ' . curl_error($ch));
    curl_close($ch);
    return false;
  }
  
  $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  
  if ($statusCode >= 200 && $statusCode < 300) {
    $accountData = json_decode($response, true);
    
    // Look for subscription credits in the plan array
    if (isset($accountData['plan']) && is_array($accountData['plan'])) {
      foreach ($accountData['plan'] as $plan) {
        if (isset($plan['type']) && $plan['type'] === 'subscription' && isset($plan['credits'])) {
          $credits = (int)$plan['credits'];
          // Cache the result
          $cachedCredits = $credits;
          $cacheTime = time();
          error_log(sprintf('Brevo subscription credits: %d', $credits));
          return $credits;
        }
      }
    }
    
    error_log('No subscription plan found in Brevo account data');
    return 0; // No subscription credits found
  }
  
  error_log(sprintf('Brevo credit check failed with status %s', $statusCode));
  return false;
}

function sendMail($subject, $body, $to)
{
  $body_ = buildEmailTemplate($body);

  // Create a new PHPMailer instance
  $mail = new PHPMailer;

  //Server settings
  // $mail->SMTPDebug = SMTP::DEBUG_SERVER; //Enable verbose debug output
  $mail->isSMTP(); //Send using SMTP
  $mail->Host = SMTP_HOST; //Set the SMTP server to send through
  $mail->SMTPAuth = true; //Enable SMTP authentication
  $mail->Username = SMTP_USERNAME; //SMTP username
  $mail->Password = SMTP_PASSWORD; //SMTP password
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; //Enable implicit TLS encryption
  $mail->Port = SMTP_PORT; //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

  //Recipients
  $mail->setFrom("contact@nivasity.com", "Nivasity");

  // Set your email subject and body
  $mail->Subject = $subject;
  $mail->isHTML(true);
  $mail->Body = $body_;
  $mail->AltBody = strip_tags($body_);

  $mail->addAddress($to);

  // Send the email
  if ($mail->send()) {
    $statusRes = "success";
  } else {
    $statusRes = "error";
  }

  return $statusRes;
}

function sendBrevoMail($subject, $body, $to, $replyToEmail = null)
{
  $body_ = buildEmailTemplate($body);

  if (!defined('BREVO_API_KEY') || !BREVO_API_KEY || !defined('BREVO_SENDER_EMAIL') || !BREVO_SENDER_EMAIL) {
    error_log('Brevo credentials are not configured. Please copy config/mail.example.php to config/mail.php and fill in BREVO_* constants.');
    return 'error';
  }

  // Check Brevo credits before attempting to send
  $credits = checkBrevoCredits();
  
  if ($credits === false) {
    error_log('Unable to check Brevo credits, falling back to default SMTP');
    return sendMail($subject, $body, $to);
  }
  
  if ($credits <= 50) {
    error_log(sprintf('Brevo credits (%d) are low (<=50), falling back to default SMTP', $credits));
    return sendMail($subject, $body, $to);
  }

  $senderName = defined('BREVO_SENDER_NAME') && BREVO_SENDER_NAME ? BREVO_SENDER_NAME : 'Nivasity';

  $payload = [
    'sender' => [
      'name' => $senderName,
      'email' => BREVO_SENDER_EMAIL,
    ],
    'to' => [
      ['email' => $to],
    ],
    'subject' => $subject,
    'htmlContent' => $body_,
  ];

  $effectiveReplyTo = null;
  if ($replyToEmail) {
    $effectiveReplyTo = $replyToEmail;
  } elseif (defined('BREVO_REPLY_TO_EMAIL') && BREVO_REPLY_TO_EMAIL) {
    $effectiveReplyTo = BREVO_REPLY_TO_EMAIL;
  }

  if ($effectiveReplyTo) {
    $payload['replyTo'] = ['email' => $effectiveReplyTo];
  }

  $encodedPayload = json_encode($payload);

  $ch = curl_init('https://api.brevo.com/v3/smtp/email');
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'content-type: application/json',
    'api-key: ' . BREVO_API_KEY,
  ]);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedPayload);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  error_log(sprintf('Brevo request initiated for %s with subject "%s" (credits: %d)', $to, $subject, $credits));

  $response = curl_exec($ch);

  if ($response === false) {
    error_log('Brevo email error: ' . curl_error($ch));
    curl_close($ch);
    return 'error';
  }

  $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $decodedResponse = json_decode($response, true);

  if ($statusCode >= 200 && $statusCode < 300) {
    $messageId = isset($decodedResponse['messageId']) ? $decodedResponse['messageId'] : 'unknown';
    error_log(sprintf('Brevo email sent successfully to %s (messageId: %s)', $to, $messageId));
    return 'success';
  }

  $errorMessage = isset($decodedResponse['message']) ? $decodedResponse['message'] : $response;
  error_log(sprintf('Brevo email failed for %s with status %s: %s', $to, $statusCode, $errorMessage));
  return 'error';
}

function sendBulkMail($subject, $body, $recipients, $replyToEmail)
{
  $body_ = buildEmailTemplate($body);

  // Create a new PHPMailer instance
  $mail = new PHPMailer;

  // Server settings
  $mail->isSMTP();
  $mail->Host = SMTP_HOST;
  $mail->SMTPAuth = true;
  $mail->Username = SMTP_USERNAME;
  $mail->Password = SMTP_PASSWORD;
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
  $mail->Port = SMTP_PORT;

  // Set sender and reply-to headers
  $mail->setFrom("contact@nivasity.com", "Nivasity");
  $mail->addReplyTo($replyToEmail, "Event Organizer");

  // Add BCC for bulk recipients
  foreach ($recipients as $email) {
    $mail->addBCC($email);
  }

  // Set your email subject and body
  $mail->Subject = $subject;
  $mail->isHTML(true);
  $mail->Body = $body_;
  $mail->AltBody = strip_tags($body_);

  // Send the email
  if ($mail->send()) {
    return "success";
  } else {
    error_log("Email failed: " . $mail->ErrorInfo); // Log the error for debugging
    return "error";
  }
}
