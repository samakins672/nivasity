-- Nivasity Marketplace Tables
-- Run this migration on the niverpay_db database

-- 1. Add marketplace-related columns to users table
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS phone_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER phone,
    ADD COLUMN IF NOT EXISTS wallet_pin_hash VARCHAR(255) NULL AFTER phone_verified;

-- 2. Marketplace listings
CREATE TABLE IF NOT EXISTS marketplace_listings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(255)    NOT NULL,
    description   TEXT            NULL,
    category      ENUM('academic','hostel','skills','food','fashion','tech','beauty') NOT NULL,
    product_type  ENUM('product','service','free') NOT NULL DEFAULT 'product',
    price         DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    original_price DECIMAL(10,2)  NULL,
    `condition`   ENUM('new','like_new','good','fair') NULL,
    status        ENUM('draft','active','sold','unpublished') NOT NULL DEFAULT 'draft',
    seller_id     INT             NOT NULL,
    school_id     INT             NOT NULL,
    course_code   VARCHAR(20)     NULL,
    stock         INT             NULL,
    is_free       TINYINT(1)      NOT NULL DEFAULT 0,
    is_moving_out TINYINT(1)      NOT NULL DEFAULT 0,
    flash_until   DATETIME        NULL,
    images        TEXT            NULL COMMENT 'JSON array of image URLs',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_school_status  (school_id, status),
    INDEX idx_seller_id      (seller_id),
    INDEX idx_category       (category),
    INDEX idx_product_type   (product_type),
    INDEX idx_created_at     (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Marketplace orders
CREATE TABLE IF NOT EXISTS marketplace_orders (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    listing_id    INT             NOT NULL,
    listing_title VARCHAR(255)    NOT NULL,
    product_type  ENUM('product','service','free') NOT NULL,
    buyer_id      INT             NOT NULL,
    seller_id     INT             NOT NULL,
    amount        DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    status        ENUM('pending','delivered','completed','cancelled','disputed') NOT NULL DEFAULT 'pending',
    escrow_locked TINYINT(1)      NOT NULL DEFAULT 0,
    delivered_at  DATETIME        NULL,
    completed_at  DATETIME        NULL,
    timeline      TEXT            NULL COMMENT 'JSON array of {status, label, timestamp}',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_buyer_id   (buyer_id),
    INDEX idx_seller_id  (seller_id),
    INDEX idx_status     (status),
    INDEX idx_listing_id (listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Marketplace reviews
CREATE TABLE IF NOT EXISTS marketplace_reviews (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    order_id     INT      NOT NULL,
    listing_id   INT      NOT NULL,
    reviewer_id  INT      NOT NULL,
    seller_id    INT      NOT NULL,
    rating       TINYINT  NOT NULL,
    comment      TEXT     NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_review (order_id),
    INDEX idx_listing_id (listing_id),
    INDEX idx_seller_id  (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Marketplace reports
CREATE TABLE IF NOT EXISTS marketplace_reports (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT     NOT NULL,
    type        ENUM('user','listing','order') NOT NULL,
    target_id   INT     NOT NULL,
    reason      ENUM('spam','fake','scam','inappropriate','harassment','other') NOT NULL,
    details     TEXT    NULL,
    status      ENUM('pending','reviewed','resolved') NOT NULL DEFAULT 'pending',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reporter_id  (reporter_id),
    INDEX idx_type_target  (type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Seller verification requests
CREATE TABLE IF NOT EXISTS marketplace_seller_verifications (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT          NOT NULL,
    id_card_path VARCHAR(500) NULL,
    nin          VARCHAR(11)  NULL,
    bvn          VARCHAR(11)  NULL,
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_at  DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Phone verification OTPs
CREATE TABLE IF NOT EXISTS phone_verification_otps (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT         NOT NULL,
    phone      VARCHAR(20) NOT NULL,
    otp_code   VARCHAR(6)  NOT NULL,
    expires_at DATETIME    NOT NULL,
    used       TINYINT(1)  NOT NULL DEFAULT 0,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
