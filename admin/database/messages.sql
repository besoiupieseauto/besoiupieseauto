CREATE TABLE IF NOT EXISTS messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    randomn_id INT UNSIGNED NULL UNIQUE,
    conversation_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    subject VARCHAR(160) NULL,
    message_body TEXT NULL,
    direction VARCHAR(20) NOT NULL DEFAULT 'inbound',
    message_status VARCHAR(30) NOT NULL DEFAULT 'new',
    channel VARCHAR(40) NOT NULL DEFAULT 'manual',
    external_conversation_id VARCHAR(190) NULL,
    external_message_id VARCHAR(190) NULL,
    delivery_status VARCHAR(30) NOT NULL DEFAULT 'received',
    bot_status VARCHAR(30) NOT NULL DEFAULT 'none',
    source_url VARCHAR(500) NULL,
    assigned_bot VARCHAR(120) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    status TINYINT(1) NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_randomn_id (randomn_id),
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_message_status (message_status),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS randomn_id INT UNSIGNED NULL UNIQUE AFTER id,
    ADD COLUMN IF NOT EXISTS conversation_id INT UNSIGNED NULL AFTER randomn_id,
    ADD COLUMN IF NOT EXISTS subject VARCHAR(160) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS message_body TEXT NULL AFTER subject,
    ADD COLUMN IF NOT EXISTS direction VARCHAR(20) NOT NULL DEFAULT 'inbound' AFTER message_body,
    ADD COLUMN IF NOT EXISTS message_status VARCHAR(30) NOT NULL DEFAULT 'new' AFTER direction,
    ADD COLUMN IF NOT EXISTS channel VARCHAR(40) NOT NULL DEFAULT 'manual' AFTER message_status,
    ADD COLUMN IF NOT EXISTS external_conversation_id VARCHAR(190) NULL AFTER channel,
    ADD COLUMN IF NOT EXISTS external_message_id VARCHAR(190) NULL AFTER external_conversation_id,
    ADD COLUMN IF NOT EXISTS delivery_status VARCHAR(30) NOT NULL DEFAULT 'received' AFTER external_message_id,
    ADD COLUMN IF NOT EXISTS bot_status VARCHAR(30) NOT NULL DEFAULT 'none' AFTER delivery_status,
    ADD COLUMN IF NOT EXISTS source_url VARCHAR(500) NULL AFTER bot_status,
    ADD COLUMN IF NOT EXISTS assigned_bot VARCHAR(120) NULL AFTER source_url,
    ADD COLUMN IF NOT EXISTS is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER assigned_bot,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE messages
SET randomn_id = id + 500000
WHERE randomn_id IS NULL;

UPDATE messages
SET conversation_id = randomn_id
WHERE conversation_id IS NULL;
