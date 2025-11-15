CREATE TABLE `support_tickets_v2` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(25) NOT NULL,                      -- public ticket code (e.g. TCK-2025-0001)
  `subject` VARCHAR(150) NOT NULL,
  `user_id` INT(11) NOT NULL,                       -- creator (from users.id)
  
  `status` ENUM('open','pending','resolved','closed') 
      NOT NULL DEFAULT 'open',
  `priority` ENUM('low','medium','high','urgent') 
      NOT NULL DEFAULT 'medium',
  `category` VARCHAR(50) DEFAULT NULL,              -- e.g. "Payments", "Login", "Events"
  
  `assigned_admin_id` INT(11) DEFAULT NULL,         -- which admin is handling it
  `last_message_at` DATETIME NOT NULL,              -- last user/admin message time
  `closed_at` DATETIME DEFAULT NULL,                -- when ticket was resolved/closed
  
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP 
      ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_ticket_code` (`code`),
  KEY `idx_ticket_user` (`user_id`),
  KEY `idx_ticket_status` (`status`),
  KEY `idx_ticket_assigned` (`assigned_admin_id`),
  CONSTRAINT `fk_ticket_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_ticket_assigned_admin`
    FOREIGN KEY (`assigned_admin_id`) REFERENCES `admins`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `support_ticket_messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  
  `sender_type` ENUM('user','admin','system') NOT NULL,
  `user_id` INT(11) DEFAULT NULL,                   -- if sender_type = 'user'
  `admin_id` INT(11) DEFAULT NULL,                  -- if sender_type = 'admin'
  
  `body` TEXT NOT NULL,
  `is_internal` TINYINT(1) NOT NULL DEFAULT 0,      -- 1 = internal note visible only to staff
  
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_msg_ticket` (`ticket_id`),
  KEY `idx_msg_user` (`user_id`),
  KEY `idx_msg_admin` (`admin_id`),
  CONSTRAINT `fk_msg_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets_v2`(`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_msg_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_msg_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `support_ticket_attachments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `message_id` INT(11) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,           -- relative path or URL
  `file_name` VARCHAR(255) NOT NULL,           -- original filename
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `file_size` INT(11) DEFAULT NULL,           -- size in bytes

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_attach_msg` (`message_id`),
  CONSTRAINT `fk_attach_msg`
    FOREIGN KEY (`message_id`) REFERENCES `support_ticket_messages`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
