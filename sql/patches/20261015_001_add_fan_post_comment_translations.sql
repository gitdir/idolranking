-- Add nullable source-language metadata and translation cache for fan-post comments.
-- Existing comments retain NULL because their original language is unknown.

ALTER TABLE fan_post_comments
    ADD COLUMN language_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NULL
        COMMENT 'ISO 639-1 original comment language; NULL for historical or unknown language';

CREATE TABLE fan_post_comment_translations (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Fan Post comment translation ID',
    comment_idx BIGINT UNSIGNED NOT NULL COMMENT 'Translated comment',
    language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Target language code',
    translated_content TEXT NOT NULL COMMENT 'Translated comment text',
    source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256 of the original comment text used for translation',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Translation cache creation time',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Translation cache update time',
    PRIMARY KEY (idx),
    UNIQUE KEY uq_fan_post_comment_translations_comment_idx_language_code (comment_idx, language_code),
    CONSTRAINT fk_fan_post_comment_translations_comment_idx FOREIGN KEY (comment_idx)
        REFERENCES fan_post_comments (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cached Fan Post comment translations by target language';
