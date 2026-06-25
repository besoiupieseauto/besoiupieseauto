CREATE TABLE IF NOT EXISTS facturi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    randomn_id INT UNSIGNED NULL UNIQUE,
    invoice_number VARCHAR(40) NULL,
    order_number VARCHAR(40) NULL,
    name VARCHAR(255) NOT NULL,
    client_name VARCHAR(160) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    payment_method VARCHAR(40) NOT NULL DEFAULT 'ramburs',
    invoice_status VARCHAR(40) NOT NULL DEFAULT 'neachitata',
    amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    due_date DATE NULL,
    notes TEXT NULL,
    status TINYINT(1) NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_randomn_id (randomn_id),
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_invoice_status (invoice_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE facturi
    ADD COLUMN IF NOT EXISTS randomn_id INT UNSIGNED NULL UNIQUE AFTER id,
    ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(40) NULL AFTER randomn_id,
    ADD COLUMN IF NOT EXISTS order_number VARCHAR(40) NULL AFTER invoice_number,
    ADD COLUMN IF NOT EXISTS client_name VARCHAR(160) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(40) NOT NULL DEFAULT 'ramburs' AFTER phone,
    ADD COLUMN IF NOT EXISTS invoice_status VARCHAR(40) NOT NULL DEFAULT 'neachitata' AFTER payment_method,
    ADD COLUMN IF NOT EXISTS amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER invoice_status,
    ADD COLUMN IF NOT EXISTS due_date DATE NULL AFTER amount,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER due_date,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE facturi
SET randomn_id = id + 300000
WHERE randomn_id IS NULL;

UPDATE facturi
SET invoice_number = CONCAT('INV-', randomn_id)
WHERE invoice_number IS NULL OR invoice_number = '';
