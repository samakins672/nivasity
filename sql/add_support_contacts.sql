-- Support Contacts Table for Mobile App
-- This table stores support contact information (WhatsApp, Email, Phone)
-- displayed to users in the mobile app

CREATE TABLE IF NOT EXISTS `support_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `whatsapp` varchar(20) DEFAULT NULL COMMENT 'WhatsApp number with country code (e.g., +2348012345678)',
  `email` varchar(255) DEFAULT NULL COMMENT 'Support email address',
  `phone` varchar(20) DEFAULT NULL COMMENT 'Support phone number with country code',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'Only active contact is shown in app',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Support contact information for mobile app';

-- Insert default support contact (update with actual values)
INSERT INTO `support_contacts` (`whatsapp`, `email`, `phone`, `status`) 
VALUES (
  '+2348000000000',  -- Replace with actual WhatsApp number
  'support@nivasity.com',  -- Replace with actual support email
  '+2348000000000',  -- Replace with actual phone number
  'active'
) ON DUPLICATE KEY UPDATE 
  updated_at = CURRENT_TIMESTAMP;

-- Usage Notes:
-- 1. Only one contact should be active at a time (the most recent one is returned)
-- 2. Update the default values above with your actual support contact information
-- 3. WhatsApp and phone numbers should include country code with + prefix
-- 4. To update support contact, insert a new row with status='active' and set old ones to 'inactive'
-- 
-- Example update query:
-- UPDATE support_contacts SET status='inactive' WHERE status='active';
-- INSERT INTO support_contacts (whatsapp, email, phone, status) VALUES ('+234...', 'new@email.com', '+234...', 'active');
