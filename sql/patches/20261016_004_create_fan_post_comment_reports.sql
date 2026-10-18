CREATE TABLE fan_post_comment_reports (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Fan Post comment report ID',
    comment_idx BIGINT UNSIGNED NOT NULL COMMENT 'Reported comment',
    member_idx BIGINT UNSIGNED NOT NULL COMMENT 'Reporting activity member',
    actor_member_idx BIGINT UNSIGNED NOT NULL COMMENT 'Authenticated actor who submitted the report',
    reason VARCHAR(32) NOT NULL COMMENT 'spam, harassment_hate, inappropriate_content, or other',
    details TEXT NULL COMMENT 'Optional context; required by the application for other',
    status VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending, reviewing, reviewed, or rejected',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_fan_post_comment_reports_comment_idx_member_idx (comment_idx, member_idx),
    KEY idx_fan_post_comment_reports_status_created_at_idx (status, created_at, idx),
    KEY idx_fan_post_comment_reports_member_idx_created_at_idx (member_idx, created_at, idx),
    KEY idx_fan_post_comment_reports_actor_member_idx_created_at_idx (actor_member_idx, created_at, idx),
    CONSTRAINT fk_fan_post_comment_reports_comment_idx FOREIGN KEY (comment_idx) REFERENCES fan_post_comments (idx),
    CONSTRAINT fk_fan_post_comment_reports_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx),
    CONSTRAINT fk_fan_post_comment_reports_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Member reports for published Fan Post comments';
