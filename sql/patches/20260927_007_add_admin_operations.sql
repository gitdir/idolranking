CREATE TABLE visitor_daily_stats (
    stat_date DATE NOT NULL,
    unique_visitors BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Privacy-friendly daily unique session visitor aggregate';

CREATE TABLE restricted_nicknames (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    word VARCHAR(50) NOT NULL,
    match_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'exact' COMMENT 'exact or contains',
    is_enabled TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_restricted_nicknames_word_match (word, match_type),
    KEY idx_restricted_nicknames_enabled_idx (is_enabled, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE blocked_ips (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    created_by_member_idx BIGINT UNSIGNED NOT NULL,
    expires_at DATETIME(6) NULL,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    KEY idx_blocked_ips_address_active_expires (ip_address, is_active, expires_at),
    KEY idx_blocked_ips_creator_created (created_by_member_idx, created_at, idx),
    CONSTRAINT fk_blocked_ips_created_by_member_idx FOREIGN KEY (created_by_member_idx) REFERENCES members (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE site_settings (
    setting_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    setting_value MEDIUMTEXT NULL,
    updated_by_member_idx BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (setting_key),
    KEY idx_site_settings_updated_by (updated_by_member_idx, updated_at),
    CONSTRAINT fk_site_settings_updated_by_member_idx FOREIGN KEY (updated_by_member_idx) REFERENCES members (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
