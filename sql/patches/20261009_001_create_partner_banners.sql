CREATE TABLE partner_banners (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL COMMENT 'Admin-only banner title and accessible image text',
    image_path VARCHAR(1024) NOT NULL COMMENT 'Owned upload under storage/partner-banners/',
    target_url VARCHAR(2048) NOT NULL,
    heart_reward INT UNSIGNED NOT NULL DEFAULT 10,
    reward_enabled TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 0,
    starts_at DATETIME(6) NULL,
    ends_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    KEY idx_partner_banners_active_order_idx (is_active, display_order, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Public partner banners and reward configuration';
