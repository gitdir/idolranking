-- Add language-tagged localization and alias storage for idols, groups, and requests.
-- Existing name/content columns remain unchanged. Do not backfill ambiguous legacy text.

CREATE TABLE idol_localizations (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idol_idx BIGINT UNSIGNED NOT NULL,
    language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    localized_name VARCHAR(200) NULL,
    description TEXT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_idol_localizations_idol_language (idol_idx, language_code),
    CONSTRAINT fk_idol_localizations_idol_idx FOREIGN KEY (idol_idx) REFERENCES idols (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reviewed localized names and descriptions for idols and groups';

CREATE TABLE idol_name_aliases (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idol_idx BIGINT UNSIGNED NOT NULL,
    language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    alias_name VARCHAR(200) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    KEY idx_idol_name_aliases_idol_language (idol_idx, language_code),
    KEY idx_idol_name_aliases_language_name (language_code, alias_name),
    CONSTRAINT fk_idol_name_aliases_idol_idx FOREIGN KEY (idol_idx) REFERENCES idols (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Additional language-tagged idol and group names';

CREATE TABLE idol_registration_request_localizations (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_idx BIGINT UNSIGNED NOT NULL,
    language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    text_role VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'submitted or reviewed; validated by application code',
    localized_name VARCHAR(200) NULL,
    description TEXT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_idol_request_localizations_request_language_role (request_idx, language_code, text_role),
    CONSTRAINT fk_idol_request_localizations_request_idx FOREIGN KEY (request_idx) REFERENCES idol_registration_requests (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Submitted and reviewed localized text for idol registration requests';
