# Notification Endpoints Documentation

## Overview
The notification system provides in-app notifications and push notifications via Expo Push Notifications. It includes device registration, notification management, and a helper library for sending notifications.

## Database Tables

### `notification_devices`
Stores push tokens for users (supports multiple devices per user).

**Columns:**
- `id` (PK, INT, AUTO_INCREMENT)
- `user_id` (FK -> users.id, INT, NOT NULL)
- `expo_push_token` (VARCHAR(255), UNIQUE, NOT NULL)
- `platform` (ENUM('android', 'ios', 'web'), NULLABLE)
- `app_version` (VARCHAR(50), NULLABLE)
- `created_at` (DATETIME, NOT NULL)
- `updated_at` (DATETIME, NOT NULL)
- `disabled_at` (DATETIME, NULLABLE)

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`expo_push_token`)
- INDEX (`user_id`, `disabled_at`)

### `notifications`
Stores in-app inbox notifications.

**Columns:**
- `id` (PK, INT, AUTO_INCREMENT)
- `user_id` (FK -> users.id, INT, NOT NULL)
- `title` (VARCHAR(255), NOT NULL)
- `body` (TEXT, NOT NULL)
- `type` (VARCHAR(50), NULLABLE) - e.g., 'order', 'payment', 'support'
- `data` (JSON/TEXT, NULLABLE) - Additional payload as JSON
- `created_at` (DATETIME, NOT NULL)
- `read_at` (DATETIME, NULLABLE)

**Indexes:**
- PRIMARY KEY (`id`)
- INDEX (`user_id`, `created_at` DESC)
- INDEX (`user_id`, `read_at`)

## Endpoints

All endpoints require JWT authentication via Bearer token in the Authorization header.

### 1. Register Device for Push Notifications

**Endpoint:** `POST /notifications/register-device.php`

**Description:** Registers or updates an Expo push token for the authenticated user. Supports multi-device (upserts based on expo_push_token).

**Request Body:**
```json
{
  "expo_push_token": "ExponentPushToken[xxxxxx]",
  "platform": "android",
  "app_version": "1.0.0"
}
```

**Parameters:**
- `expo_push_token` (string, required): Expo push token from the device
- `platform` (string, optional): One of 'android', 'ios', or 'web'
- `app_version` (string, optional): App version string

**Response (200 OK):**
```json
{
  "status": "success",
  "message": "Device registered",
  "data": {
    "ok": true
  }
}
```

**Error Responses:**
- `400 Bad Request`: Missing or invalid expo_push_token
- `401 Unauthorized`: Invalid or missing authentication token

---

### 2. Unregister Device

**Endpoint:** `POST /notifications/unregister-device.php`

**Description:** Soft-disables a device's push token (sets disabled_at timestamp).

**Request Body:**
```json
{
  "expo_push_token": "ExponentPushToken[xxxxxx]"
}
```

**Parameters:**
- `expo_push_token` (string, required): The token to unregister

**Response (200 OK):**
```json
{
  "status": "success",
  "message": "Device unregistered",
  "data": {
    "ok": true
  }
}
```

---

### 3. List Notifications

**Endpoint:** `GET /notifications/list.php`

**Description:** Returns paginated list of notifications for the authenticated user, sorted newest first. Includes unread count.

**Query Parameters:**
- `page` (int, optional, default: 1): Page number (min 1)
- `limit` (int, optional, default: 50): Results per page (min 1, max 100)

**Response (200 OK):**
```json
{
  "status": "success",
  "message": "Notifications loaded",
  "data": {
    "unread_count": 3,
    "notifications": [
      {
        "id": 101,
        "title": "Order confirmed",
        "body": "Your order #A123 has been confirmed.",
        "type": "order",
        "data": {
          "order_id": 123
        },
        "created_at": "2026-01-21 12:30:10",
        "read_at": null
      },
      {
        "id": 100,
        "title": "Payment received",
        "body": "Your payment of ₦5000 has been received.",
        "type": "payment",
        "data": {
          "tx_ref": "nivas_123_1234567890",
          "amount": 5000
        },
        "created_at": "2026-01-21 10:15:00",
        "read_at": "2026-01-21 11:00:00"
      }
    ],
    "page": 1,
    "limit": 50
  }
}
```

**Notes:**
- `read_at` is `null` for unread notifications
- `data` is a JSON object parsed from the stored JSON string

---

### 4. Mark Notification as Read

**Endpoint:** `POST /notifications/mark-read.php`

**Description:** Marks a single notification as read by setting the read_at timestamp.

**Request Body:**
```json
{
  "id": "101"
}
```

**Parameters:**
- `id` (int|string, required): Notification ID to mark as read

**Response (200 OK):**
```json
{
  "status": "success",
  "message": "Notification marked as read",
  "data": {
    "ok": true
  }
}
```

**Notes:**
- Only marks the notification if it belongs to the authenticated user
- No-op if already read (still returns success)

---

### 5. Mark All Notifications as Read

**Endpoint:** `POST /notifications/mark-all-read.php`

**Description:** Marks all unread notifications as read for the authenticated user.

**Request Body:**
```json
{}
```

**Response (200 OK):**
```json
{
  "status": "success",
  "message": "Marked 3 notifications as read",
  "data": {
    "ok": true,
    "count": 3
  }
}
```

**Notes:**
- `count` indicates how many notifications were marked as read

---

## Helper Functions

The `model/notifications.php` file provides helper functions for creating and sending notifications:

### `notifyUser($conn, $user_id, $title, $body, $type = null, $data = null)`

Creates a notification in the database and sends a push notification to all active devices for the user.

**Parameters:**
- `$conn` (mysqli): Database connection
- `$user_id` (int): User ID to notify
- `$title` (string): Notification title
- `$body` (string): Notification body
- `$type` (string, optional): Notification type (e.g., 'order', 'payment')
- `$data` (array, optional): Additional data as associative array

**Returns:**
```php
[
  'success' => true,
  'notification_id' => 123,
  'push_result' => [
    'success' => true,
    'message' => 'Push notifications sent successfully'
  ]
]
```

**Example Usage:**
```php
require_once __DIR__ . '/../model/notifications.php';

$result = notifyUser(
    $conn,
    $user_id,
    'Payment Confirmed',
    'Your payment of ₦5000 has been confirmed.',
    'payment',
    ['tx_ref' => 'nivas_123_1234567890', 'amount' => 5000]
);
```

### `notifyMultipleUsers($conn, $user_ids, $title, $body, $type = null, $data = null)`

Creates notifications for multiple users (fan-out on write) and sends push notifications to all their devices.

**Parameters:**
- `$conn` (mysqli): Database connection
- `$user_ids` (array): Array of user IDs to notify
- `$title` (string): Notification title
- `$body` (string): Notification body
- `$type` (string, optional): Notification type
- `$data` (array, optional): Additional data as associative array

**Returns:**
```php
[
  'success' => true,
  'created_count' => 10,
  'push_count' => 15,
  'push_result' => [...]
]
```

**Example Usage:**
```php
// Notify all users in a school
$result = notifyMultipleUsers(
    $conn,
    [1, 2, 3, 4, 5],
    'System Maintenance',
    'The system will be down for maintenance on Sunday.',
    'system',
    ['maintenance_date' => '2026-01-25']
);
```

### `createNotification($conn, $user_id, $title, $body, $type = null, $data = null)`

Creates a notification in the database without sending a push notification.

**Returns:** Notification ID on success, false on failure

### `sendPushNotification($conn, $user_id, $title, $body, $data = null)`

Sends push notification to all active devices for a user without creating a database record.

**Returns:** Array with 'success' and 'message'

---

## Integration with Expo Push Notifications

The system integrates with Expo's push notification service:

**Expo API Endpoint:** `https://exp.host/--/api/v2/push/send`

**Message Format:**
```json
{
  "to": "ExponentPushToken[xxxxxx]",
  "title": "Order confirmed",
  "body": "Your order #A123 has been confirmed.",
  "sound": "default",
  "priority": "high",
  "data": {
    "notification_id": 101,
    "type": "order",
    "order_id": 123
  }
}
```

**Important Notes:**
- The system always includes `notification_id` in the push data so the app can highlight the corresponding notification in the inbox
- Messages are sent in batches of up to 100
- Failed tokens (e.g., `DeviceNotRegistered`) are logged
- Push delivery is best-effort; the `notifications` table is the source of truth

---

## Usage Examples

### When a payment is completed:

```php
require_once __DIR__ . '/../model/notifications.php';

// After successful payment verification
$result = notifyUser(
    $conn,
    $user_id,
    'Payment Successful',
    "Your payment of ₦{$amount} has been confirmed.",
    'payment',
    [
        'tx_ref' => $tx_ref,
        'amount' => $amount,
        'payment_url' => 'nivasity://payment/' . $tx_ref
    ]
);
```

### When a support ticket is updated:

```php
require_once __DIR__ . '/../model/notifications.php';

$result = notifyUser(
    $conn,
    $user_id,
    'Support Ticket Updated',
    "Your ticket #{$ticket_id} has a new response.",
    'support',
    [
        'ticket_id' => $ticket_id,
        'ticket_url' => 'nivasity://support/ticket/' . $ticket_id
    ]
);
```

### Broadcast to all users in a school:

```php
require_once __DIR__ . '/../model/notifications.php';

// Get all user IDs in a school
$query = "SELECT id FROM users WHERE school_id = ? AND role IN ('student', 'hoc')";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$user_ids = [];
while ($row = mysqli_fetch_assoc($result)) {
    $user_ids[] = $row['id'];
}

// Send notification to all
$result = notifyMultipleUsers(
    $conn,
    $user_ids,
    'New Materials Available',
    'New study materials have been added for your courses.',
    'materials',
    ['school_id' => $school_id]
);
```

---

## Error Handling

All endpoints follow the standard API error response format:

```json
{
  "status": "error",
  "message": "Error description"
}
```

Common HTTP status codes:
- `200 OK`: Success
- `400 Bad Request`: Invalid input
- `401 Unauthorized`: Missing or invalid authentication
- `405 Method Not Allowed`: Wrong HTTP method
- `500 Internal Server Error`: Server-side error

All errors are also logged via `error_log()` for debugging.
