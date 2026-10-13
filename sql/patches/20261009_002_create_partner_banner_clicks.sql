CREATE TABLE partner_banner_clicks (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    banner_idx BIGINT UNSIGNED NOT NULL,
    member_idx BIGINT UNSIGNED NULL,
    clicked_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    KEY idx_partner_banner_clicks_banner_idx_clicked_at (banner_idx, clicked_at),
    KEY idx_partner_banner_clicks_member_idx_clicked_at (member_idx, clicked_at),
    CONSTRAINT fk_partner_banner_clicks_banner_idx
        FOREIGN KEY (banner_idx) REFERENCES partner_banners (idx) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
COMMENT='Activated partner banner clicks from guests and eligible normal members only; same subject/banner repeats within two seconds are ignored';
