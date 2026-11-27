ALTER TABLE fan_posts
    MODIFY COLUMN idol_idx BIGINT UNSIGNED NULL
        COMMENT 'Target individual or group; NULL for Other Fan Posts';
