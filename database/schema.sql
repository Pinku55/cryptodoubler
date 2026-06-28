-- =====================================================================
-- MTASK - Database Schema
-- MySQL 8+ / utf8mb4. Imported automatically by install.php.
-- All money/points stored as BIGINT whole MT units to avoid float drift.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- settings : key/value application configuration
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- admins : admin panel accounts
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(64) NOT NULL,
    `email`      VARCHAR(190) NULL,
    `password`   VARCHAR(255) NOT NULL,
    `role`       ENUM('super_admin','admin','moderator') NOT NULL DEFAULT 'admin',
    `status`     ENUM('active','disabled') NOT NULL DEFAULT 'active',
    `last_login` DATETIME NULL,
    `last_ip`    VARCHAR(45) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- admin_logs : audit trail
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`   INT UNSIGNED NULL,
    `action`     VARCHAR(100) NOT NULL,
    `details`    TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_logs_admin` (`admin_id`),
    KEY `idx_admin_logs_action` (`action`),
    CONSTRAINT `fk_admin_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- users : Telegram users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `telegram_id`      BIGINT NOT NULL,
    `username`         VARCHAR(64) NULL,
    `first_name`       VARCHAR(128) NULL,
    `last_name`        VARCHAR(128) NULL,
    `language`         VARCHAR(10) NOT NULL DEFAULT 'en',
    `photo_url`        VARCHAR(512) NULL,
    `referral_code`    VARCHAR(16) NOT NULL,
    `referred_by`      BIGINT UNSIGNED NULL,
    `balance`          BIGINT NOT NULL DEFAULT 0,
    `total_earned`     BIGINT NOT NULL DEFAULT 0,
    `total_withdrawn`  BIGINT NOT NULL DEFAULT 0,
    `total_referrals`  INT UNSIGNED NOT NULL DEFAULT 0,
    `daily_streak`     INT UNSIGNED NOT NULL DEFAULT 0,
    `last_bonus_date`  DATE NULL,
    `status`           ENUM('active','banned') NOT NULL DEFAULT 'active',
    `last_ip`          VARCHAR(45) NULL,
    `register_ip`      VARCHAR(45) NULL,
    `last_login`       DATETIME NULL,
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_telegram` (`telegram_id`),
    UNIQUE KEY `uq_users_refcode` (`referral_code`),
    KEY `idx_users_referred_by` (`referred_by`),
    KEY `idx_users_status` (`status`),
    KEY `idx_users_register_ip` (`register_ip`),
    KEY `idx_users_created` (`created_at`),
    CONSTRAINT `fk_users_referrer` FOREIGN KEY (`referred_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- transactions : balance ledger (powers wallet + activity feeds)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `type`          VARCHAR(32) NOT NULL,
    `amount`        BIGINT NOT NULL,
    `balance_after` BIGINT NOT NULL DEFAULT 0,
    `note`          VARCHAR(255) NULL,
    `meta`          JSON NULL,
    `status`        ENUM('pending','completed','failed') NOT NULL DEFAULT 'completed',
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tx_user` (`user_id`),
    KEY `idx_tx_type` (`type`),
    KEY `idx_tx_created` (`created_at`),
    CONSTRAINT `fk_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- tasks : configurable task offers
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tasks` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`        VARCHAR(190) NOT NULL,
    `description`  TEXT NULL,
    `category`     ENUM('website','shortlink','telegram_channel','telegram_group','telegram_bot','instagram','facebook','twitter','youtube','survey','other') NOT NULL DEFAULT 'website',
    `url`          VARCHAR(512) NULL,
    `image`        VARCHAR(255) NULL,
    `reward`       BIGINT NOT NULL DEFAULT 0,
    `wait_time`    INT UNSIGNED NOT NULL DEFAULT 10,  -- seconds before claim
    `daily_limit`  INT UNSIGNED NOT NULL DEFAULT 0,   -- 0 = once per user lifetime
    `total_limit`  INT UNSIGNED NOT NULL DEFAULT 0,   -- 0 = unlimited completions
    `completed_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `verify_type`  ENUM('auto','timer','telegram_member','none') NOT NULL DEFAULT 'timer',
    `verify_target` VARCHAR(190) NULL,                -- e.g. @channel for membership check
    `status`       ENUM('active','disabled') NOT NULL DEFAULT 'active',
    `sort_order`   INT NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tasks_status` (`status`),
    KEY `idx_tasks_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- task_completions : per-user task completion / progress
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `task_completions` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id`      INT UNSIGNED NOT NULL,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `status`       ENUM('started','completed') NOT NULL DEFAULT 'started',
    `reward`       BIGINT NOT NULL DEFAULT 0,
    `started_at`   DATETIME NULL,
    `completed_at` DATETIME NULL,
    `ip_address`   VARCHAR(45) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_tc_task` (`task_id`),
    KEY `idx_tc_user` (`user_id`),
    KEY `idx_tc_user_task` (`user_id`,`task_id`),
    CONSTRAINT `fk_tc_task` FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tc_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ad_views : rewarded ad watch records (anti-spam + daily limits)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ad_views` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `reward`     BIGINT NOT NULL DEFAULT 0,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_adviews_user` (`user_id`),
    KEY `idx_adviews_created` (`created_at`),
    CONSTRAINT `fk_adviews_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- daily_bonus_rewards : configurable 7-day reward ladder
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `daily_bonus_rewards` (
    `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `day`    TINYINT UNSIGNED NOT NULL,
    `reward` BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dbr_day` (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- payment_methods : withdrawal channels with dynamic fields
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `code`        VARCHAR(50) NOT NULL,
    `icon`        VARCHAR(255) NULL,
    `min_amount`  BIGINT NOT NULL DEFAULT 20000,
    `fields`      JSON NULL,        -- [{name,label,type,required}]
    `status`      ENUM('active','disabled') NOT NULL DEFAULT 'active',
    `sort_order`  INT NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pm_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- withdrawals : withdrawal requests
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `withdrawals` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `method_id`    INT UNSIGNED NULL,
    `method_name`  VARCHAR(100) NULL,
    `amount_mt`    BIGINT NOT NULL,
    `amount_usd`   DECIMAL(12,4) NOT NULL DEFAULT 0,
    `account_details` JSON NULL,
    `status`       ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
    `admin_note`   VARCHAR(255) NULL,
    `processed_by` INT UNSIGNED NULL,
    `processed_at` DATETIME NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_wd_user` (`user_id`),
    KEY `idx_wd_status` (`status`),
    KEY `idx_wd_created` (`created_at`),
    CONSTRAINT `fk_wd_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- rate_limits : DB-backed throttling buckets
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bucket`   VARCHAR(190) NOT NULL,
    `hits`     INT UNSIGNED NOT NULL DEFAULT 0,
    `reset_at` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rl_bucket` (`bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- notifications : in-app / broadcast notifications
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NULL,    -- NULL = broadcast
    `title`      VARCHAR(190) NOT NULL,
    `body`       TEXT NULL,
    `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
