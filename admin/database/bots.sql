CREATE TABLE IF NOT EXISTS bots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    randomn_id INT UNSIGNED NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    bot_type VARCHAR(60) NOT NULL DEFAULT 'message_sender',
    channel VARCHAR(40) NOT NULL DEFAULT 'manual',
    token_value TEXT NULL,
    token_status VARCHAR(30) NOT NULL DEFAULT 'active',
    token_plan VARCHAR(20) NOT NULL DEFAULT 'free',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    requests_limit INT UNSIGNED NULL,
    requests_used INT UNSIGNED NOT NULL DEFAULT 0,
    webhook_url VARCHAR(500) NULL,
    test_url VARCHAR(500) NULL,
    last_test_status VARCHAR(30) NULL,
    last_test_message VARCHAR(500) NULL,
    last_test_at DATETIME NULL,
    notes TEXT NULL,
    status TINYINT(1) NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_randomn_id (randomn_id),
    INDEX idx_channel (channel),
    INDEX idx_token_status (token_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bots
    ADD COLUMN IF NOT EXISTS randomn_id INT UNSIGNED NULL UNIQUE AFTER id,
    ADD COLUMN IF NOT EXISTS bot_type VARCHAR(60) NOT NULL DEFAULT 'message_sender' AFTER phone,
    ADD COLUMN IF NOT EXISTS channel VARCHAR(40) NOT NULL DEFAULT 'manual' AFTER bot_type,
    ADD COLUMN IF NOT EXISTS token_value TEXT NULL AFTER channel,
    ADD COLUMN IF NOT EXISTS token_status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER token_value,
    ADD COLUMN IF NOT EXISTS token_plan VARCHAR(20) NOT NULL DEFAULT 'free' AFTER token_status,
    ADD COLUMN IF NOT EXISTS starts_at DATETIME NULL AFTER token_plan,
    ADD COLUMN IF NOT EXISTS ends_at DATETIME NULL AFTER starts_at,
    ADD COLUMN IF NOT EXISTS requests_limit INT UNSIGNED NULL AFTER ends_at,
    ADD COLUMN IF NOT EXISTS requests_used INT UNSIGNED NOT NULL DEFAULT 0 AFTER requests_limit,
    ADD COLUMN IF NOT EXISTS webhook_url VARCHAR(500) NULL AFTER requests_used,
    ADD COLUMN IF NOT EXISTS test_url VARCHAR(500) NULL AFTER webhook_url,
    ADD COLUMN IF NOT EXISTS last_test_status VARCHAR(30) NULL AFTER test_url,
    ADD COLUMN IF NOT EXISTS last_test_message VARCHAR(500) NULL AFTER last_test_status,
    ADD COLUMN IF NOT EXISTS last_test_at DATETIME NULL AFTER last_test_message,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER last_test_at,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE bots
SET randomn_id = id + 600000
WHERE randomn_id IS NULL;
