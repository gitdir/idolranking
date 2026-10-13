CREATE TABLE IF NOT EXISTS password_reset_tokens (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_idx BIGINT UNSIGNED NOT NULL,
    token_hash BINARY(32) NOT NULL COMMENT 'SHA-256 binary digest of a single-use reset token; never store the raw token',
    expires_at DATETIME(6) NOT NULL,
    used_at DATETIME(6) NULL,
    requested_ip VARCHAR(45) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_password_reset_hash (token_hash),
    KEY idx_password_reset_member_idx_used_at_expires_at (member_idx, used_at, expires_at),
    KEY idx_password_reset_expires_at_idx (expires_at, idx),
    CONSTRAINT fk_password_reset_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Single-use password reset token digests with expiration';
