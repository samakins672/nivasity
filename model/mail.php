<?php
require('../config/mail.php');

//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;

function sendMail($subject, $body, $to) {
    
  // HTML Email Template
  $body_ = '
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
              <img src="https://stage.nivasity.com/assets/images/nivasity-main.png" alt="Nivasty">
          </div>
          <div class="content">
              '.$body.'
          </div>
      </div>
      <div class="footer">
          <p>For any feedback or inquiries, get in touch with us at<br>
              <a href="mailto:support@nivasity.com">support@nivasity.com</a> <br> <br>

              Nivasity\'s services are provided by Nivasity Web Services.<br>
              A business duly incorporated under the laws of Nigeria. <br> <br><br>

              Copyright © Nivasity. 2024 All rights reserved.<br>
      </div>
  </body>

  </html>';

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

?>