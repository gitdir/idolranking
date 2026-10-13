ALTER TABLE member_access_logs
    MODIFY COLUMN visitor_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'Random first-party visitor identifier; NULL for detected bots',
    ADD COLUMN is_bot TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER visitor_id,
    ADD KEY idx_member_access_bot_created_at_idx (is_bot, created_at, idx);
