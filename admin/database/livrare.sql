CREATE TABLE IF NOT EXISTS livrare (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    randomn_id INT UNSIGNED NULL UNIQUE,
    awb VARCHAR(80) NULL,
    order_number VARCHAR(40) NULL,
    name VARCHAR(255) NOT NULL,
    client_name VARCHAR(160) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    address VARCHAR(255) NULL,
    courier VARCHAR(80) NOT NULL DEFAULT 'Fan Courier',
    service_type VARCHAR(80) NULL,
    delivery_status VARCHAR(40) NOT NULL DEFAULT 'pregatire',
    delivery_date DATE NULL,
    delivery_time VARCHAR(20) NULL,
    total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    status TINYINT(1) NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_randomn_id (randomn_id),
    INDEX idx_awb (awb),
    INDEX idx_delivery_status (delivery_status),
    INDEX idx_courier (courier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE livrare
    ADD COLUMN IF NOT EXISTS randomn_id INT UNSIGNED NULL UNIQUE AFTER id,
    ADD COLUMN IF NOT EXISTS awb VARCHAR(80) NULL AFTER randomn_id,
    ADD COLUMN IF NOT EXISTS order_number VARCHAR(40) NULL AFTER awb,
    ADD COLUMN IF NOT EXISTS client_name VARCHAR(160) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS courier VARCHAR(80) NOT NULL DEFAULT 'Fan Courier' AFTER address,
    ADD COLUMN IF NOT EXISTS service_type VARCHAR(80) NULL AFTER courier,
    ADD COLUMN IF NOT EXISTS delivery_status VARCHAR(40) NOT NULL DEFAULT 'pregatire' AFTER service_type,
    ADD COLUMN IF NOT EXISTS delivery_date DATE NULL AFTER delivery_status,
    ADD COLUMN IF NOT EXISTS delivery_time VARCHAR(20) NULL AFTER delivery_date,
    ADD COLUMN IF NOT EXISTS total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER delivery_time,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER total_amount,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE livrare
SET randomn_id = id + 400000
WHERE randomn_id IS NULL;

UPDATE livrare
SET awb = CONCAT('AWB-', randomn_id)
WHERE awb IS NULL OR awb = '';
