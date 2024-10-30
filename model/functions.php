<?php
function generateVerificationCode($length) {
    // Generate a random verification code of the specified length
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function isCodeUnique($code, $conn, $db_table) {
    // Check if the code already exists in the table
    $query = "SELECT COUNT(*) as count FROM $db_table WHERE code = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    $stmt->close();

    return $count == 0; // If count is 0, the code is unique
}

function sendCongratulatoryEmail($conn, $user_id, $tx_ref, $cart_, $cart_2, $total_amount) {
    // Fetch user details
    $user_ = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
    $user = mysqli_fetch_array($user_);
    $to = $user['email'];
    $user_name = $user['first_name']; // Assuming 'name' column exists
    $subject = "Congratulations on Your Purchase!";
    $message = "Hello $user_name,<br>Thank you for your purchase!<br><br>";

    // Check if cart_ has items, then add manuals section
    if (!empty($cart_)) {
        $message .= "Materials:<ol>";
        foreach ($cart_ as $manual_id) {
            $manual_query = mysqli_query($conn, "SELECT * FROM manuals WHERE id = $manual_id");
            if ($manual_query && mysqli_num_rows($manual_query) > 0) {
                $manual = mysqli_fetch_array($manual_query);
                $message .= "<li>Title - " . htmlspecialchars($manual['title']) . " (" . htmlspecialchars($manual['course_code']) . ")<br>";
                $message .= "Price - ₦ " . number_format($manual['price'], 2) . "</li>";
            }
        }
    }

    // Check if cart_2 has items, then add events section
    if (!empty($cart_2)) {
        $message .= "</ol>Events:<ol>";
        foreach ($cart_2 as $event_id) {
            $event_query = mysqli_query($conn, "SELECT * FROM events WHERE id = $event_id");
            if ($event_query && mysqli_num_rows($event_query) > 0) {
                $event = mysqli_fetch_array($event_query);
                $message .= "<li>Event Title - " . htmlspecialchars($event['title']) . "<br>";
                $message .= "Location - " . htmlspecialchars($event['location']) . "<br>";
                $message .= "Date - " . date('j M', strtotime($event['event_date'])) . " • " . date('g:i A', strtotime($event['event_time'])) . "<br>";
                $message .= "Price - ₦ " . number_format($event['price'], 2) . "</li><br>";
            }
        }
    }

    $message .= "</ol>Total Amount: ₦ " . number_format($total_amount, 2) . "<br>";
    $message .= "Ref ID: #$tx_ref <br><br>"; // Unique reference ID for each email
    $message .= "We hope you enjoy your purchase!<br><br>Best regards,<br>Nivasity Team";

    // Send email
    sendMail($subject, $message, $to);
}

?>