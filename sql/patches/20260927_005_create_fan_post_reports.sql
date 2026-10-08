CREATE TABLE fan_post_reports (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Fan Post report ID',
    fan_post_idx BIGINT UNSIGNED NOT NULL COMMENT 'Reported Fan Post',
    member_idx BIGINT UNSIGNED NOT NULL COMMENT 'Reporting member',
    reason VARCHAR(32) NOT NULL COMMENT 'spam, harassment_hate, inappropriate_content, copyright, misinformation, or other',
    details TEXT NULL COMMENT 'Optional report details; required by the application for other',
    status VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending, reviewed, or rejected',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_fan_post_reports_fan_post_idx_member_idx (fan_post_idx, member_idx),
    KEY idx_fan_post_reports_status_created_at_idx (status, created_at, idx),
    KEY idx_fan_post_reports_member_idx_created_at_idx (member_idx, created_at, idx),
    CONSTRAINT fk_fan_post_reports_fan_post_idx FOREIGN KEY (fan_post_idx) REFERENCES fan_posts (idx),
    CONSTRAINT fk_fan_post_reports_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Member reports for published Fan Posts';
