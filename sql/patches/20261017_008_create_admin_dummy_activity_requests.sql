-- Durable request-to-dummy mapping for administrator-initiated random activity mode.
CREATE TABLE admin_dummy_activity_requests (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_member_idx BIGINT UNSIGNED NOT NULL,
    member_idx BIGINT UNSIGNED NOT NULL,
    action_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    result_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    result_idx BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    completed_at DATETIME(6) NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_admin_dummy_requests_actor_action_key (actor_member_idx, action_code, request_key),
    KEY idx_admin_dummy_requests_member_action_created (member_idx, action_code, created_at, idx),
    CONSTRAINT fk_admin_dummy_requests_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx),
    CONSTRAINT fk_admin_dummy_requests_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
