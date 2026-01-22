# Notification Integration Guide

This document describes how notifications have been integrated into the Nivasity application.

## Overview

Notifications are automatically sent to users for the following events:
1. **Payment Success** - When a payment is successfully verified
2. **Material Uploads** - When new study materials are uploaded (to students in same school/dept)
3. **Support Ticket Replies** - When admin/support replies to a ticket
4. **Support Ticket Status Changes** - When a ticket status is updated
5. **System Notifications** - Manual notifications from admins

## 1. Payment Notifications

**Integrated in:**
- `API/payment/callback.php` (line ~176)
- `API/payment/verify.php` (line ~175)

**Trigger:** After successful payment verification

**Implementation:**
```php
notifyUser(
    $conn,
    $user_id,
    'Payment Successful',
    "Your payment of ₦" . number_format($amount, 2) . " has been confirmed.",
    'payment',
    [
        'tx_ref' => $tx_ref,
        'amount' => $amount,
        'status' => 'success'
    ]
);
```

**Notification data includes:**
- Transaction reference
- Payment amount
- Status

## 2. Material Upload Notifications

**Helper function:** `notifyMaterialUpload()` in `model/notification_helpers.php`

**Usage:** Call this function after a material is successfully uploaded

**Example integration (in manual upload handler):**
```php
require_once __DIR__ . '/../../model/notification_helpers.php';

// After material is inserted into database
$manual_id = mysqli_insert_id($conn);
notifyMaterialUpload($conn, $manual_id, $uploader_id);
```

**Who receives:** All active students in the same school and department as the material

**Notification data includes:**
- Manual ID
- Title and course code
- Price
- Uploader name

### Integration Points for Material Uploads

To integrate material upload notifications, add the following code to these files:

**admin/model/manuals.php** - After successful manual creation (around line where manual is inserted):
```php
// After this line: mysqli_query($conn, "INSERT INTO manuals ...")
if (mysqli_affected_rows($conn) >= 1) {
    $manual_id = mysqli_insert_id($conn);
    
    // Send notifications to students
    require_once __DIR__ . '/../../model/notification_helpers.php';
    notifyMaterialUpload($conn, $manual_id, $user_id);
}
```

## 3. Support Ticket Notifications

**Helper functions:** 
- `notifySupportTicketReply()` - For replies from admin/support
- `notifySupportTicketStatusChange()` - For status changes

**Current status:** User replies already notify support via email. Admin replies need integration.

**Usage for admin replies:**
```php
require_once __DIR__ . '/../../model/notification_helpers.php';

// After admin adds a reply to ticket
notifySupportTicketReply($conn, $ticket_id, $ticket_owner_id, $admin_name);
```

**Usage for status changes:**
```php
require_once __DIR__ . '/../../model/notification_helpers.php';

// After ticket status is updated
notifySupportTicketStatusChange($conn, $ticket_id, $user_id, 'resolved');
```

### Integration Points for Support Tickets

**When admin replies to a ticket** - Add to admin support reply handler:
```php
// After admin message is inserted
notifySupportTicketReply($conn, $ticket_id, $ticket_owner_id, 'Support Team');
```

**When ticket status changes** - Add to status update handler:
```php
// After status UPDATE query
if ($new_status === 'resolved' || $new_status === 'closed') {
    notifySupportTicketStatusChange($conn, $ticket_id, $user_id, $new_status);
}
```

## 4. Admin System Notifications

**Endpoint:** `POST /notifications/admin/send.php`

**Authentication:** Requires admin email and password from `admins` table

**Password format:** Can be sent as either:
- Plain text password (will be hashed automatically)
- MD5 hash of the password (32-character hexadecimal string)

**Request format:**
```json
{
  "email": "admin@school.edu",
  "password": "5f4dcc3b5aa765d61d8327deb882cf99",  // MD5 hash (recommended) or plain text
  "title": "Important Announcement",
  "body": "Classes will resume on Monday.",
  "type": "general",
  "data": {"key": "value"},
  
  // Target options (use ONE of these):
  "user_id": 123,                    // Single user
  "user_ids": [1, 2, 3],             // Multiple specific users
  "school_id": 1,                    // All users in a school
  "broadcast": true                   // All active users (system-wide)
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Notifications sent successfully",
  "data": {
    "notifications_created": 50,
    "push_sent": true,
    "recipients": 50
  }
}
```

**Targeting options:**
- `user_id` - Send to a single user
- `user_ids` - Array of user IDs to notify
- `school_id` - All active users in a specific school
- `broadcast: true` - All active users in the system

**Example usage:**

School-wide announcement (with MD5 hashed password):
```bash
curl -X POST https://api.nivasity.com/notifications/admin/send.php \
  -H "Content-Type: application/json" \
  -d '{
    "email": "hoc@school.edu",
    "password": "5f4dcc3b5aa765d61d8327deb882cf99",
    "title": "Exam Schedule Released",
    "body": "The final exam schedule has been published. Check your portal.",
    "type": "announcement",
    "school_id": 1
  }'
```

Single user notification (with plain text password):
```bash
curl -X POST https://api.nivasity.com/notifications/admin/send.php \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@school.edu",
    "password": "mypassword",
    "title": "Your Request Approved",
    "body": "Your request has been approved by the admin.",
    "type": "general",
    "user_id": 45
  }'
```

**Note:** For better security, it's recommended to send the MD5 hash of the password rather than plain text.

## Summary of Integration Status

| Feature | Status | Location |
|---------|--------|----------|
| Payment notifications | ✅ Integrated | API/payment/callback.php, verify.php |
| Material upload notifications | ⚠️ Helper ready | model/notification_helpers.php |
| Support ticket replies | ⚠️ Helper ready | model/notification_helpers.php |
| Support status changes | ⚠️ Helper ready | model/notification_helpers.php |
| Admin manual notifications | ✅ Complete | API/notifications/admin/send.php |

✅ = Fully integrated and working
⚠️ = Helper function created, needs integration in admin panel

## Notes

1. **Material uploads**: The helper function is ready. Integration requires adding the function call to the admin panel where materials are created.

2. **Support tickets**: The helper functions are ready for:
   - Admin replies to tickets
   - Status changes (resolved, closed, etc.)
   
   These need to be integrated in the admin panel where admins manage support tickets.

3. **Admin endpoint security**: Uses email + password authentication from the `admins` table. Password can be sent as MD5 hash (32-character hex string) or plain text. Only active admins can send notifications.

4. **All notifications**: 
   - Create a database record in the `notifications` table
   - Send push notification via Expo to all registered devices
   - Include relevant data for the mobile app to handle

## Testing

Test payment notifications:
1. Make a test payment
2. Check user's notification inbox via `GET /notifications/list.php`
3. Verify push notification is received on mobile device

Test admin notifications:
```bash
# Test with your admin credentials
# You can send password as MD5 hash (recommended) or plain text
curl -X POST http://localhost/API/notifications/admin/send.php \
  -H "Content-Type: application/json" \
  -d '{
    "email": "your_admin@email.com",
    "password": "your_md5_hash_or_plain_password",
    "title": "Test Notification",
    "body": "This is a test notification",
    "user_id": YOUR_USER_ID
  }'
```

To get MD5 hash of your password:
```bash
echo -n "your_password" | md5sum
```

## Future Enhancements

Potential additional notification triggers:
- Material price changes
- Event reminders
- Settlement processed
- Account verification
- Password reset
- New announcements from school
