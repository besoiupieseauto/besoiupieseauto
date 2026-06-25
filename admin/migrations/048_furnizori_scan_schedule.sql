-- Program sincronizare per furnizor (interval / oră fixă / fereastră orară / manual)
ALTER TABLE `furnizori`
    ADD COLUMN `scan_schedule_mode` VARCHAR(20) NOT NULL DEFAULT 'interval' AFTER `scan_interval_minutes`,
    ADD COLUMN `scan_schedule_time` VARCHAR(5) NULL DEFAULT '06:00' AFTER `scan_schedule_mode`,
    ADD COLUMN `scan_window_start` VARCHAR(5) NULL DEFAULT '08:00' AFTER `scan_schedule_time`,
    ADD COLUMN `scan_window_end` VARCHAR(5) NULL DEFAULT '18:00' AFTER `scan_window_start`,
    ADD COLUMN `scan_auto_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `scan_window_end`;
