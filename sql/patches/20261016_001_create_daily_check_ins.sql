-- Add text-based Daily Check-in storage and configurable reward ranges.
-- Existing Heart history rows are intentionally not converted into check-in posts.

ALTER TABLE heart_reward_settings
    ADD COLUMN min_heart_amount BIGINT UNSIGNED NULL AFTER heart_amount,
    ADD COLUMN max_heart_amount BIGINT UNSIGNED NULL AFTER min_heart_amount;

-- Preserve the current fixed Daily Check-in reward as an equivalent min/max range.
UPDATE heart_reward_settings
SET min_heart_amount = heart_amount,
    max_heart_amount = heart_amount
WHERE action_code = 'daily_attendance';

CREATE TABLE daily_check_ins (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_idx BIGINT UNSIGNED NOT NULL,
    actor_member_idx BIGINT UNSIGNED NOT NULL,
    language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'Original message language; NULL when unknown',
    content VARCHAR(500) NOT NULL,
    awarded_hearts BIGINT UNSIGNED NOT NULL,
    check_in_date DATE NOT NULL COMMENT 'UTC calendar date used for the daily uniqueness rule',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_daily_check_ins_member_idx_check_in_date (member_idx, check_in_date),
    KEY idx_daily_check_ins_check_in_date_idx (check_in_date, idx),
    KEY idx_daily_check_ins_actor_member_idx_idx (actor_member_idx, idx),
    CONSTRAINT fk_daily_check_ins_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx),
    CONSTRAINT fk_daily_check_ins_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
