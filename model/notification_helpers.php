<?php
/**
 * Material Upload Notification Helper
 * 
 * Include this file and call notifyMaterialUpload() when a new material is uploaded
 * This will send notifications to all students in the relevant school/department
 */

require_once __DIR__ . '/notifications.php';

/**
 * Send notification when a new material is uploaded
 * 
 * @param mysqli $conn Database connection
 * @param int $manual_id The ID of the uploaded manual
 * @param int $uploader_id The ID of the user who uploaded the material
 * @return array Result with notification count and push status
 */
function notifyMaterialUpload($conn, $manual_id, $uploader_id) {
    // Get material details
    $manual_query = mysqli_query($conn, "SELECT m.*, u.first_name, u.last_name, u.school, u.dept 
        FROM manuals m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.id = $manual_id 
        LIMIT 1");
    
    if (mysqli_num_rows($manual_query) === 0) {
        error_log("notifyMaterialUpload: Manual $manual_id not found");
        return ['success' => false, 'error' => 'Manual not found'];
    }
    
    $manual = mysqli_fetch_assoc($manual_query);
    
    // Get all active students in the same school and department
    $school_id = (int)$manual['school'];
    $dept_id = (int)$manual['dept'];
    
    $user_ids = [];
    $users_query = mysqli_query($conn, "SELECT id FROM users 
        WHERE school = $school_id 
        AND dept = $dept_id 
        AND role = 'student' 
        AND status = 'active'
        AND id != $uploader_id");
    
    while ($user = mysqli_fetch_assoc($users_query)) {
        $user_ids[] = (int)$user['id'];
    }
    
    if (empty($user_ids)) {
        error_log("notifyMaterialUpload: No students found for manual $manual_id in school $school_id, dept $dept_id");
        return ['success' => true, 'recipients' => 0, 'message' => 'No students to notify'];
    }
    
    // Prepare notification
    $title = 'New Study Material Available';
    $body = "New material: {$manual['title']} ({$manual['course_code']}) has been uploaded.";
    $type = 'materials';
    $data = [
        'manual_id' => $manual_id,
        'title' => $manual['title'],
        'course_code' => $manual['course_code'],
        'price' => $manual['price'],
        'uploader' => $manual['first_name'] . ' ' . $manual['last_name']
    ];
    
    // Send notifications to all students
    $result = notifyMultipleUsers($conn, $user_ids, $title, $body, $type, $data);
    
    error_log("notifyMaterialUpload: Sent notification for manual $manual_id to " . count($user_ids) . " students");
    
    return array_merge(['success' => true], $result);
}

/**
 * Send notification when a support ticket gets a reply from admin/support
 * 
 * @param mysqli $conn Database connection
 * @param int $ticket_id The ticket ID
 * @param int $user_id The user who owns the ticket
 * @param string $replier_name Name of the person who replied
 * @return array Result with notification ID and push status
 */
function notifySupportTicketReply($conn, $ticket_id, $user_id, $replier_name = 'Support Team') {
    // Get ticket details
    $ticket_query = mysqli_query($conn, "SELECT * FROM support_tickets_v2 WHERE id = $ticket_id LIMIT 1");
    
    if (mysqli_num_rows($ticket_query) === 0) {
        error_log("notifySupportTicketReply: Ticket $ticket_id not found");
        return ['success' => false, 'error' => 'Ticket not found'];
    }
    
    $ticket = mysqli_fetch_assoc($ticket_query);
    
    // Prepare notification
    $title = 'Support Ticket Reply';
    $body = "You have a new reply on your ticket: {$ticket['subject']}";
    $type = 'support';
    $data = [
        'ticket_id' => $ticket_id,
        'ticket_code' => $ticket['code'],
        'subject' => $ticket['subject'],
        'replier' => $replier_name
    ];
    
    // Send notification
    $result = notifyUser($conn, $user_id, $title, $body, $type, $data);
    
    error_log("notifySupportTicketReply: Sent notification for ticket $ticket_id to user $user_id");
    
    return array_merge(['success' => true], $result);
}

/**
 * Send notification when a support ticket status changes
 * 
 * @param mysqli $conn Database connection
 * @param int $ticket_id The ticket ID
 * @param int $user_id The user who owns the ticket
 * @param string $new_status The new status (resolved, closed, etc.)
 * @return array Result with notification ID and push status
 */
function notifySupportTicketStatusChange($conn, $ticket_id, $user_id, $new_status) {
    // Get ticket details
    $ticket_query = mysqli_query($conn, "SELECT * FROM support_tickets_v2 WHERE id = $ticket_id LIMIT 1");
    
    if (mysqli_num_rows($ticket_query) === 0) {
        error_log("notifySupportTicketStatusChange: Ticket $ticket_id not found");
        return ['success' => false, 'error' => 'Ticket not found'];
    }
    
    $ticket = mysqli_fetch_assoc($ticket_query);
    
    // Prepare notification
    $title = 'Support Ticket Updated';
    $body = "Your ticket '{$ticket['subject']}' has been marked as $new_status.";
    $type = 'support';
    $data = [
        'ticket_id' => $ticket_id,
        'ticket_code' => $ticket['code'],
        'subject' => $ticket['subject'],
        'status' => $new_status
    ];
    
    // Send notification
    $result = notifyUser($conn, $user_id, $title, $body, $type, $data);
    
    error_log("notifySupportTicketStatusChange: Sent notification for ticket $ticket_id status change to $new_status");
    
    return array_merge(['success' => true], $result);
}
?>
