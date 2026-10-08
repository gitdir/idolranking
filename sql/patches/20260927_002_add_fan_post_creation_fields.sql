ALTER TABLE fan_posts
    ADD COLUMN title VARCHAR(100) NULL AFTER actor_member_idx,
    ADD COLUMN youtube_video_id CHAR(11) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER image_path;

UPDATE fan_posts
SET title = CONCAT('Fan Post #', idx)
WHERE title IS NULL OR title = '';

ALTER TABLE fan_posts
    MODIFY COLUMN title VARCHAR(100) NOT NULL COMMENT 'Fan Post title';

CREATE TABLE fan_post_tags (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Fan Post tag ID',
    fan_post_idx BIGINT UNSIGNED NOT NULL COMMENT 'Tagged Fan Post',
    tag VARCHAR(30) NOT NULL COMMENT 'Display tag without the # prefix',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Tag creation time',
    PRIMARY KEY (idx),
    UNIQUE KEY uq_fan_post_tags_fan_post_idx_tag (fan_post_idx, tag),
    KEY idx_fan_post_tags_tag_fan_post_idx (tag, fan_post_idx),
    CONSTRAINT fk_fan_post_tags_fan_post_idx FOREIGN KEY (fan_post_idx) REFERENCES fan_posts (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Fan Post display and search tags';
