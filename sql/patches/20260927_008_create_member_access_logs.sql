CREATE TABLE member_access_logs (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Site access log ID',
    member_idx BIGINT UNSIGNED NULL COMMENT 'Authenticated member; NULL for anonymous visitors',
    visitor_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Keyed hash of the first-party session identifier',
    ip_address VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_path VARCHAR(1024) NOT NULL COMMENT 'Request path only; query strings are excluded',
    user_agent VARCHAR(1024) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    KEY idx_member_access_created_at_idx (created_at, idx),
    KEY idx_member_access_member_idx_created_at_idx (member_idx, created_at, idx),
    KEY idx_member_access_ip_created_at_idx (ip_address, created_at, idx),
    CONSTRAINT fk_member_access_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Recent public page access records with limited retention';
