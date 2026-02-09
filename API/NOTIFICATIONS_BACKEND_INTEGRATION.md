# Nivasity Notifications (Backend Integration Guide)

This mobile app is wired for **Expo Push Notifications** + an **in-app notification inbox**. To make it work end-to-end, implement the endpoints below and store notifications per user.

## Overview (how it works)

1. The app requests push permission (user-controlled).
2. If granted, the app gets an **Expo Push Token** and calls **`POST /notifications/register-device.php`**.
3. Your backend stores the token for the authenticated user.
4. When something important happens (order/payment/support/etc), the backend:
   - creates a notification record in DB
   - sends a push via **Expo Push API** to all registered tokens for that user
5. The app shows the push (OS-level) and also renders it in the in-app **Notifications** screen via **`GET /notifications/list.php`**.

## Targeting (system-wide + groups)

This setup supports:
- **Single user** notifications (typical): send to all device tokens for one `user_id`.
- **Group-wide** notifications: send to users matching a filter (examples: `school_id`, `dept_id`, `role`, `level`).
- **System-wide** notifications: send to all active device tokens.

Notes:
- Expo Push does **not** provide “topics” like native FCM topics. Targeting is handled by your backend by selecting which users/tokens to send to.
- For the in-app inbox, you must also decide how to store broadcast/group notifications:
  - **Option A (simplest / recommended): fan-out on write**  
    Create one `notifications` row per recipient user. Existing `list.php`, `mark-read.php`, and unread counts remain simple.
  - **Option B (scales better for very large broadcasts): fan-out on read**  
    Store one broadcast notification plus an `audience` (or `notification_audiences`) definition, and track reads in a separate table (`notification_reads`). Your `list.php` must return both direct + broadcast notifications that match the user.

## Data model (recommended)

### `notification_devices`
Stores push tokens for a user (multi-device supported).

Suggested fields:
- `id` (PK)
- `user_id` (FK -> users.id)
- `expo_push_token` (unique, required)
- `platform` (`android` | `ios` | `web`, optional)
- `app_version` (optional)
- `created_at`, `updated_at`
- `disabled_at` (nullable) or `is_active` (bool)

Notes:
- Treat `expo_push_token` as the primary unique identifier for an installation.
- Upsert on `expo_push_token` so re-registering doesn't create duplicates.

### `notifications`
Stores the in-app inbox.

Suggested fields:
- `id` (PK)
- `user_id` (FK -> users.id)
- `title` (string)
- `body` (string)
- `type` (string, optional; e.g. `order`, `payment`, `support`)
- `data` (JSON, optional; additional payload)
- `created_at` (datetime)
- `read_at` (datetime, nullable)

Indexes:
- (`user_id`, `created_at` DESC)
- (`user_id`, `read_at`)

## Endpoints (expected by the app)

All endpoints below are **JWT-protected** (same auth as other endpoints).

### 1) Register device for push
`POST /notifications/register-device.php`

Body (JSON):
```json
{
  "expo_push_token": "ExponentPushToken[xxxxxx]",
  "platform": "android",
  "app_version": "1.0.0"
}
```

Behavior:
- Validate token shape.
- Upsert into `notification_devices` for the authenticated user.
- Re-activate if previously disabled.

Response (success):
```json
{ "status": "success", "message": "Device registered", "data": { "ok": true } }
```

### 2) Unregister device (optional but recommended)
`POST /notifications/unregister-device.php`

Body (JSON):
```json
{ "expo_push_token": "ExponentPushToken[xxxxxx]" }
```

Behavior:
- Soft-disable (`disabled_at`) or delete the token record for that user.

### 3) List notifications (in-app inbox)
`GET /notifications/list.php?page=1&limit=50`

Response (success):
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
        "data": { "order_id": 123 },
        "created_at": "2026-01-21 12:30:10",
        "read_at": null
      }
    ]
  }
}
```

Notes:
- Sort newest-first.
- `read_at = null` means unread.
- You can include pagination fields if you want; the app currently uses the first page.

### 4) Mark one notification as read
`POST /notifications/mark-read.php`

Body (JSON):
```json
{ "id": "101" }
```

Behavior:
- Verify the notification belongs to the authenticated user.
- Set `read_at = NOW()` if not already set.

### 5) Mark all as read
`POST /notifications/mark-all-read.php`

Body: `{}` (empty JSON is fine)

Behavior:
- Set `read_at = NOW()` for all unread notifications for the user.

## Sending push via Expo (server-side)

### Expo endpoint
- `POST https://exp.host/--/api/v2/push/send`

Send in batches (max 100 messages per request).

Message example:
```json
{
  "to": "ExponentPushToken[xxxxxx]",
  "title": "Order confirmed",
  "body": "Your order #A123 has been confirmed.",
  "sound": "default",
  "data": {
    "notification_id": 101,
    "type": "order",
    "order_id": 123
  }
}
```

Important:
- Always include `notification_id` in `data`. The app uses it to highlight the notification in the inbox.
- Handle Expo “tickets” and “receipts” to detect invalid tokens (e.g. `DeviceNotRegistered`) and disable/remove them.

### Reliability notes
- Push delivery is “best effort” (OS-controlled). The source of truth must be the `notifications` table.
- The app fetches the inbox on login and when it comes back to the foreground.

## Optional: deep links / routing

If you want a tap to open specific screens, add routing metadata into `data` (e.g. `order_id`, `ticket_id`). The app can be extended to navigate based on these fields; currently it always opens the in-app Notifications screen.
