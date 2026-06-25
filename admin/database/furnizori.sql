CREATE TABLE IF NOT EXISTS furnizori (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    randomn_id INT UNSIGNED NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',

    price_markup_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
    price_markup_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_round_to DECIMAL(10,2) NULL,
    price_min_margin DECIMAL(10,2) NULL,

    stock_zero_mode VARCHAR(30) NOT NULL DEFAULT 'full',
    scan_include_zero_stock TINYINT(1) NOT NULL DEFAULT 1,
    scan_skip_unavailable TINYINT(1) NOT NULL DEFAULT 0,

    connection_type VARCHAR(20) NOT NULL DEFAULT 'ftp',
    scan_interval_minutes INT UNSIGNED NOT NULL DEFAULT 60,
    scan_schedule_mode VARCHAR(20) NOT NULL DEFAULT 'interval',
    scan_schedule_time VARCHAR(5) NULL DEFAULT '06:00',
    scan_window_start VARCHAR(5) NULL DEFAULT '08:00',
    scan_window_end VARCHAR(5) NULL DEFAULT '18:00',
    scan_auto_enabled TINYINT(1) NOT NULL DEFAULT 1,

    conn_host VARCHAR(255) NULL,
    conn_port INT UNSIGNED NULL,
    conn_username VARCHAR(255) NULL,
    conn_password TEXT NULL,
    conn_remote_path VARCHAR(500) NULL,
    conn_passive TINYINT(1) NOT NULL DEFAULT 1,

    conn_email VARCHAR(255) NULL,
    conn_email_inbox VARCHAR(255) NULL,
    conn_imap_host VARCHAR(255) NULL,
    conn_imap_port INT UNSIGNED NULL DEFAULT 993,
    conn_email_password TEXT NULL,
    conn_create_inbox TINYINT(1) NOT NULL DEFAULT 0,

    api_base_url VARCHAR(500) NULL,
    api_token TEXT NULL,

    last_scan_at DATETIME NULL,
    last_scan_status VARCHAR(30) NULL,
    last_scan_message VARCHAR(500) NULL,
    last_test_status VARCHAR(30) NULL,
    last_test_message VARCHAR(500) NULL,
    last_test_at DATETIME NULL,
    products_count INT UNSIGNED NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_furnizori_randomn_id (randomn_id),
    INDEX idx_furnizori_status (status),
    INDEX idx_furnizori_connection_type (connection_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
