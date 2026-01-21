<?php
/**
 * Notification Helper Functions
 * 
 * Functions for creating notifications and sending push notifications via Expo
 */

/**
 * Create a notification for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $title Notification title
 * @param string $body Notification body
 * @param string|null $type Notification type (e.g., 'order', 'payment', 'support')
 * @param array|null $data Additional data as associative array
 * @return int|false Notification ID on success, false on failure
 */
function createNotification($conn, $user_id, $title, $body, $type = null, $data = null) {
    $data_json = $data ? json_encode($data) : null;
    
    $query = "INSERT INTO notifications (user_id, title, body, type, data, created_at) 
              VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'issss', $user_id, $title, $body, $type, $data_json);
    
    if (mysqli_stmt_execute($stmt)) {
        $notification_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return $notification_id;
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Send push notification via Expo Push API
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID to send to
 * @param string $title Notification title
 * @param string $body Notification body
 * @param array|null $data Additional data
 * @return array Result with 'success' boolean and 'message'
 */
function sendPushNotification($conn, $user_id, $title, $body, $data = null) {
    // Get all active device tokens for this user
    $tokens_query = "SELECT expo_push_token FROM notification_devices 
                    WHERE user_id = ? AND disabled_at IS NULL";
    $stmt = mysqli_prepare($conn, $tokens_query);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $tokens = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tokens[] = $row['expo_push_token'];
    }
    mysqli_stmt_close($stmt);
    
    if (empty($tokens)) {
        return ['success' => false, 'message' => 'No active devices found for user'];
    }
    
    // Send to Expo Push API
    return sendExpoPushNotifications($tokens, $title, $body, $data);
}

/**
 * Send push notifications to multiple tokens via Expo Push API
 * 
 * @param array $tokens Array of Expo push tokens
 * @param string $title Notification title
 * @param string $body Notification body
 * @param array|null $data Additional data
 * @return array Result with 'success' boolean and 'message'
 */
function sendExpoPushNotifications($tokens, $title, $body, $data = null) {
    if (empty($tokens)) {
        return ['success' => false, 'message' => 'No tokens provided'];
    }
    
    // Prepare messages (Expo supports batching up to 100 messages)
    $messages = [];
    foreach ($tokens as $token) {
        $message = [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'priority' => 'high'
        ];
        
        if ($data) {
            $message['data'] = $data;
        }
        
        $messages[] = $message;
    }
    
    // Split into batches of 100
    $batches = array_chunk($messages, 100);
    $all_successful = true;
    $error_messages = [];
    
    foreach ($batches as $batch) {
        $result = sendExpoBatch($batch);
        if (!$result['success']) {
            $all_successful = false;
            $error_messages[] = $result['message'];
        }
    }
    
    if ($all_successful) {
        return ['success' => true, 'message' => 'Push notifications sent successfully'];
    } else {
        return [
            'success' => false, 
            'message' => 'Some push notifications failed: ' . implode('; ', $error_messages)
        ];
    }
}

/**
 * Send a batch of messages to Expo Push API
 * 
 * @param array $messages Array of message objects
 * @return array Result with 'success' boolean and 'message'
 */
function sendExpoBatch($messages) {
    $expo_url = 'https://exp.host/--/api/v2/push/send';
    
    $ch = curl_init($expo_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messages));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log("Expo Push cURL error: $curl_error");
        return ['success' => false, 'message' => 'Network error sending push notification'];
    }
    
    if ($http_code !== 200) {
        error_log("Expo Push API returned status $http_code: $response");
        return ['success' => false, 'message' => "Expo API error (HTTP $http_code)"];
    }
    
    $response_data = json_decode($response, true);
    
    // Check for errors in the response
    if (isset($response_data['data'])) {
        $has_errors = false;
        foreach ($response_data['data'] as $ticket) {
            if ($ticket['status'] === 'error') {
                $has_errors = true;
                $error_details = isset($ticket['details']) ? $ticket['details']['error'] : 'Unknown error';
                error_log("Expo Push error: $error_details");
                
                // Handle specific errors (e.g., DeviceNotRegistered)
                // In production, you might want to disable tokens that return certain errors
            }
        }
        
        if ($has_errors) {
            return ['success' => false, 'message' => 'Some push notifications had errors'];
        }
    }
    
    return ['success' => true, 'message' => 'Batch sent successfully'];
}

/**
 * Create notification and send push notification
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $title Notification title
 * @param string $body Notification body
 * @param string|null $type Notification type
 * @param array|null $data Additional data
 * @return array Result with 'success', 'notification_id', and 'push_result'
 */
function notifyUser($conn, $user_id, $title, $body, $type = null, $data = null) {
    // Create notification in database
    $notification_id = createNotification($conn, $user_id, $title, $body, $type, $data);
    
    if (!$notification_id) {
        return [
            'success' => false,
            'message' => 'Failed to create notification'
        ];
    }
    
    // Add notification_id to data for push
    $push_data = $data ?: [];
    $push_data['notification_id'] = $notification_id;
    
    // Send push notification
    $push_result = sendPushNotification($conn, $user_id, $title, $body, $push_data);
    
    return [
        'success' => true,
        'notification_id' => $notification_id,
        'push_result' => $push_result
    ];
}

/**
 * Create notifications for multiple users (fan-out on write)
 * 
 * @param mysqli $conn Database connection
 * @param array $user_ids Array of user IDs
 * @param string $title Notification title
 * @param string $body Notification body
 * @param string|null $type Notification type
 * @param array|null $data Additional data
 * @return array Result with 'success', 'created_count', and 'push_count'
 */
function notifyMultipleUsers($conn, $user_ids, $title, $body, $type = null, $data = null) {
    $created_count = 0;
    $push_tokens = [];
    
    // Create notification for each user and collect their tokens
    foreach ($user_ids as $user_id) {
        $notification_id = createNotification($conn, $user_id, $title, $body, $type, $data);
        
        if ($notification_id) {
            $created_count++;
            
            // Get user's device tokens
            $tokens_query = "SELECT expo_push_token FROM notification_devices 
                            WHERE user_id = ? AND disabled_at IS NULL";
            $stmt = mysqli_prepare($conn, $tokens_query);
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $push_tokens[] = $row['expo_push_token'];
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Send push notifications
    $push_result = ['success' => true, 'message' => 'No tokens to push'];
    if (!empty($push_tokens)) {
        $push_data = $data ?: [];
        $push_result = sendExpoPushNotifications($push_tokens, $title, $body, $push_data);
    }
    
    return [
        'success' => true,
        'created_count' => $created_count,
        'push_count' => count($push_tokens),
        'push_result' => $push_result
    ];
}
