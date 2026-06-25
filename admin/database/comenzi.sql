CREATE TABLE IF NOT EXISTS comenzi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    randomn_id INT UNSIGNED NULL UNIQUE,
    order_number VARCHAR(40) NULL,
    name VARCHAR(255) NOT NULL,
    product_image VARCHAR(500) NULL,
    client_name VARCHAR(160) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    vin VARCHAR(40) NULL,
    channel VARCHAR(40) NOT NULL DEFAULT 'website',
    payment_status VARCHAR(40) NOT NULL DEFAULT 'ramburs',
    delivery_method VARCHAR(80) NULL,
    delivery_status VARCHAR(80) NULL,
    order_status VARCHAR(40) NOT NULL DEFAULT 'noua',
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    status TINYINT(1) NULL DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_randomn_id (randomn_id),
    INDEX idx_order_number (order_number),
    INDEX idx_order_status (order_status),
    INDEX idx_channel (channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE comenzi
    ADD COLUMN IF NOT EXISTS randomn_id INT UNSIGNED NULL UNIQUE AFTER id,
    ADD COLUMN IF NOT EXISTS order_number VARCHAR(40) NULL AFTER randomn_id,
    ADD COLUMN IF NOT EXISTS product_image VARCHAR(500) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS client_name VARCHAR(160) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS vin VARCHAR(40) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS channel VARCHAR(40) NOT NULL DEFAULT 'website' AFTER vin,
    ADD COLUMN IF NOT EXISTS payment_status VARCHAR(40) NOT NULL DEFAULT 'ramburs' AFTER channel,
    ADD COLUMN IF NOT EXISTS delivery_method VARCHAR(80) NULL AFTER payment_status,
    ADD COLUMN IF NOT EXISTS delivery_status VARCHAR(80) NULL AFTER delivery_method,
    ADD COLUMN IF NOT EXISTS order_status VARCHAR(40) NOT NULL DEFAULT 'noua' AFTER delivery_status,
    ADD COLUMN IF NOT EXISTS quantity INT UNSIGNED NOT NULL DEFAULT 1 AFTER order_status,
    ADD COLUMN IF NOT EXISTS total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER quantity,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE comenzi
SET randomn_id = id + 200000
WHERE randomn_id IS NULL;

UPDATE comenzi
SET order_number = CONCAT('ORD-', randomn_id)
WHERE order_number IS NULL OR order_number = '';
