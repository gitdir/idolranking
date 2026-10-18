-- Cache public Daily Check-in translations without changing original messages.
-- Apply after 20261016_001_create_daily_check_ins.sql on existing installations.

CREATE TABLE daily_check_in_translations (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    daily_check_in_idx BIGINT UNSIGNED NOT NULL,
    source_language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    translated_content TEXT NOT NULL,
    source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256 of the exact original check-in message',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_daily_check_in_translations_check_in_source_target (daily_check_in_idx, source_language_code, target_language_code),
    CONSTRAINT fk_daily_check_in_translations_check_in_idx FOREIGN KEY (daily_check_in_idx) REFERENCES daily_check_ins (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cached Daily Check-in translations';
