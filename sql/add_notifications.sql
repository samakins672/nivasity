-- SQL Schema for Expo Push Notifications Feature
-- This adds support for push notifications and in-app notification inbox

-- --------------------------------------------------------

--
-- Table structure for table `notification_devices`
-- Stores Expo push tokens for user devices
--

CREATE TABLE IF NOT EXISTS `notification_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `expo_push_token` varchar(255) NOT NULL,
  `platform` enum('android','ios','web') DEFAULT NULL,
  `app_version` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `disabled_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expo_push_token` (`expo_push_token`),
  KEY `user_id` (`user_id`),
  KEY `disabled_at` (`disabled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
-- Stores in-app notifications for users
--

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'general',
  `data` text DEFAULT NULL COMMENT 'JSON encoded data',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  KEY `read_at` (`read_at`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Foreign key constraints
--

ALTER TABLE `notification_devices`
  ADD CONSTRAINT `notification_devices_ibfk_1` 
  FOREIGN KEY (`user_id`) 
  REFERENCES `users` (`id`) 
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` 
  FOREIGN KEY (`user_id`) 
  REFERENCES `users` (`id`) 
  ON DELETE CASCADE ON UPDATE CASCADE;

-- --------------------------------------------------------

--
-- Indexes for performance optimization
--

-- Composite index for fetching user's unread notifications
ALTER TABLE `notifications` 
  ADD INDEX `idx_user_unread` (`user_id`, `read_at`, `created_at`);

-- Index for fetching active devices for a user
ALTER TABLE `notification_devices` 
  ADD INDEX `idx_user_active` (`user_id`, `disabled_at`);

-- --------------------------------------------------------

--
-- Sample notification types (for reference)
--
-- Common notification types used in the system:
-- - 'payment' : Payment successful notifications
-- - 'material' : New material uploaded notifications
-- - 'support' : Support ticket updates
-- - 'announcement' : System-wide announcements
-- - 'general' : General notifications
-- - 'event' : Event-related notifications

-- --------------------------------------------------------

--
-- Usage Notes
--
-- 1. notification_devices table:
--    - Stores Expo push tokens for each user device
--    - Supports multiple devices per user
--    - Uses UPSERT logic on expo_push_token (unique constraint)
--    - Soft delete via disabled_at timestamp
--
-- 2. notifications table:
--    - Stores in-app notification inbox messages
--    - Fan-out on write approach (one row per recipient)
--    - read_at NULL means unread notification
--    - data field stores JSON for custom payloads
--
-- 3. Notification flow:
--    a) User registers device via POST /notifications/register-device.php
--    b) System creates notification record when event occurs
--    c) System sends push via Expo API to all user's active devices
--    d) User views notification inbox via GET /notifications/list.php
--    e) User marks as read via POST /notifications/mark-read.php
--
-- 4. Integration points:
--    - Payment webhooks: handle-ps-webhook.php, handle-fw-webhook.php, handle-isw-webhook.php
--    - Payment verification: verify-pending-payment.php, callback.php, verify.php
--    - Admin notifications: POST /notifications/admin/send.php
--    - Material uploads: notifyMaterialUpload() helper function
--    - Support tickets: notifySupportTicketReply(), notifySupportTicketStatusChange()

COMMIT;
