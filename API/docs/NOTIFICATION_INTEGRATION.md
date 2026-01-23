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

**Action payload for deep linking:**
```php
[
    'action' => 'order_receipt',
    'tx_ref' => $tx_ref,
    'amount' => $amount,
    'status' => 'success'
]
```

This allows the mobile app to navigate directly to the payment receipt screen when user taps the notification.

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

**Action payload for deep linking:**
```php
[
    'action' => 'material_details',
    'manual_id' => $manual_id,
    'title' => $manual['title'],
    'course_code' => $manual['course_code'],
    'price' => $manual['price'],
    'uploader' => $uploader_name
]
```

This allows the mobile app to navigate directly to the material details screen when user taps the notification.

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

**Action payloads for deep linking:**

Reply notification:
```php
[
    'action' => 'support_ticket',
    'ticket_id' => $ticket_id,
    'ticket_code' => $ticket['code'],
    'subject' => $ticket['subject'],
    'replier' => $replier_name
]
```

Status change notification:
```php
[
    'action' => 'support_ticket',
    'ticket_id' => $ticket_id,
    'ticket_code' => $ticket['code'],
    'subject' => $ticket['subject'],
    'status' => $new_status
]
```

These allow the mobile app to navigate directly to the support ticket detail screen when user taps the notification.

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

**Password format:** Must be MD5 hash (32-character hexadecimal string)

**Role requirement:** Admin role must be 1, 2, or 3

**Request format:**
```json
{
  "email": "admin@school.edu",
  "password": "5f4dcc3b5aa765d61d8327deb882cf99",  // MD5 hash (required)
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

School-wide announcement:
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

Single user notification:
```bash
curl -X POST https://api.nivasity.com/notifications/admin/send.php \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@school.edu",
    "password": "098f6bcd4621d373cade4e832627b4f6",
    "title": "Your Request Approved",
    "body": "Your request has been approved by the admin.",
    "type": "general",
    "user_id": 45
  }'
```

**Generating MD5 hash:**
```bash
# On Linux/Mac
echo -n "your_password" | md5sum

# On PHP
echo md5("your_password");

# JavaScript/Node.js
const crypto = require('crypto');
const hash = crypto.createHash('md5').update('your_password').digest('hex');
```

## Summary of Integration Status

| Feature | Status | Location |
|---------|--------|----------|
| Payment notifications | ✅ Integrated | API/payment/callback.php, verify.php |
| Material upload notifications | ⚠️ Helper ready | model/notification_helpers.php |
| Support ticket replies | ⚠️ Helper ready | model/notification_helpers.php |
| Support status changes | ⚠️ Helper ready | model/notification_helpers.php |
| Admin manual notifications | ✅ Complete | API/notifications/admin/send.php |
| Deep linking action payloads | ✅ Complete | All notifications include action data |

✅ = Fully integrated and working
⚠️ = Helper function created, needs integration in admin panel

## Mobile App Deep Linking

All notifications now include action payloads that enable deep linking. When users tap on a notification (push or in-app), the mobile app can navigate directly to the relevant screen.

### How It Works

**Backend:** Every notification includes an `action` field and relevant identifiers in the `data` JSON field.

**Mobile App:** Handle notification taps using Expo's notification listener.

### Implementation in Mobile App (Expo)

```javascript
import * as Notifications from 'expo-notifications';
import { useNavigation } from '@react-navigation/native';

// Setup notification handler
Notifications.addNotificationResponseReceivedListener(response => {
  const data = response.notification.request.content.data;
  
  // Handle different notification actions
  switch(data.action) {
    case 'order_receipt':
      // Navigate to payment receipt screen
      navigation.navigate('PaymentReceipt', { 
        tx_ref: data.tx_ref,
        amount: data.amount
      });
      break;
      
    case 'material_details':
      // Navigate to material details screen
      navigation.navigate('MaterialDetails', { 
        manual_id: data.manual_id,
        title: data.title
      });
      break;
      
    case 'support_ticket':
      // Navigate to support ticket screen
      navigation.navigate('TicketDetails', { 
        ticket_id: data.ticket_id,
        ticket_code: data.ticket_code
      });
      break;
      
    default:
      // Unknown action or general notification
      navigation.navigate('Notifications');
      break;
  }
});
```

### Action Types and Data

**1. Payment Receipts** (`action: 'order_receipt'`)
```json
{
  "action": "order_receipt",
  "tx_ref": "NVS-123456",
  "amount": 5000,
  "status": "success"
}
```

**2. Material Details** (`action: 'material_details'`)
```json
{
  "action": "material_details",
  "manual_id": 789,
  "title": "Introduction to Computer Science",
  "course_code": "CSC101",
  "price": 1500,
  "uploader": "Prof. John Doe"
}
```

**3. Support Tickets** (`action: 'support_ticket'`)
```json
{
  "action": "support_ticket",
  "ticket_id": 123,
  "ticket_code": "TKT-001",
  "subject": "Payment Issue",
  "replier": "Support Team"
}
```

### Testing Deep Linking

**Test payment notification deep link:**
1. Make a payment via the API
2. Tap on the push notification or in-app notification
3. Verify navigation to payment receipt screen with transaction details

**Test material notification deep link:**
1. Admin uploads a new material (once integrated)
2. Student receives notification
3. Tap notification → navigates to material details screen

**Test support ticket deep link:**
1. Admin replies to a support ticket (once integrated)
2. User receives notification
3. Tap notification → navigates to ticket conversation

### Handling In-App Notification List

The notification list endpoint (`GET /notifications/list.php`) returns notifications with the `data` field parsed as JSON:

```json
{
  "notifications": [
    {
      "id": 123,
      "title": "Payment Successful",
      "body": "Your payment of ₦5,000.00 has been confirmed.",
      "type": "payment",
      "data": {
        "action": "order_receipt",
        "tx_ref": "NVS-123456",
        "amount": 5000
      },
      "read_at": null,
      "created_at": "2026-01-22 10:15:00"
    }
  ]
}
```

When user taps an in-app notification, use the same navigation logic:

```javascript
const handleNotificationTap = (notification) => {
  if (notification.data?.action === 'order_receipt') {
    navigation.navigate('PaymentReceipt', { 
      tx_ref: notification.data.tx_ref 
    });
  }
  // ... handle other actions
};
```

### Benefits

✅ **Better UX** - Users land directly on relevant screens
✅ **Payment receipts** - Quick access to transaction details
✅ **Material discovery** - Direct navigation to new uploads
✅ **Support engagement** - Immediate access to ticket updates
✅ **Consistent** - Works for both push and in-app notifications
✅ **Extensible** - Easy to add new action types

## Notes

1. **Material uploads**: The helper function is ready. Integration requires adding the function call to the admin panel where materials are created.

2. **Support tickets**: The helper functions are ready for:
   - Admin replies to tickets
   - Status changes (resolved, closed, etc.)
   
   These need to be integrated in the admin panel where admins manage support tickets.

3. **Admin endpoint security**: Uses email + password authentication from the `admins` table. Password must be MD5 hash (32-character hex string). Only active admins with role 1, 2, or 3 can send notifications.

4. **All notifications**: 
   - Create a database record in the `notifications` table
   - Send push notification via Expo to all registered devices
   - Include action payload for deep linking
   - Data field allows mobile app to navigate to relevant screens

5. **Deep linking**: All notifications automatically include action payloads (payment, material, support) enabling direct navigation when users tap notifications.

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
- Material price changes (with deep link to material details)
- Event reminders (with deep link to event details)
- Settlement processed (with deep link to settlement history)
- Account verification (with deep link to verification screen)
- Password reset (with deep link to reset screen)
- New announcements from school (with deep link to announcements)
