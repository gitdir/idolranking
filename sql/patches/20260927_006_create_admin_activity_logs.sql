CREATE TABLE admin_activity_logs (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_member_idx BIGINT UNSIGNED NOT NULL COMMENT 'Administrator who performed the action',
    action VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_idx BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(1000) NULL COMMENT 'Internal moderation reason',
    summary VARCHAR(1000) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    KEY idx_admin_activity_actor_created (actor_member_idx, created_at, idx),
    KEY idx_admin_activity_target_created (target_type, target_idx, created_at, idx),
    KEY idx_admin_activity_action_created (action, created_at, idx),
    CONSTRAINT fk_admin_activity_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Audit trail for administrator moderation actions';
