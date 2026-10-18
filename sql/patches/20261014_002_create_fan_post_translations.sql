CREATE TABLE `fan_post_translations` (
  `idx` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Fan Post translation ID',
  `fan_post_idx` BIGINT UNSIGNED NOT NULL COMMENT 'Translated Fan Post',
  `language_code` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Target language code',
  `translated_title` VARCHAR(100) NOT NULL COMMENT 'Translated Fan Post title',
  `translated_content` TEXT NULL COMMENT 'Translated Fan Post content',
  `source_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256 of the original title and content used for this translation',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Translation cache creation time',
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Translation cache update time',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_fan_post_translations_fan_post_idx_language_code` (`fan_post_idx`, `language_code`),
  CONSTRAINT `fk_fan_post_translations_fan_post_idx` FOREIGN KEY (`fan_post_idx`) REFERENCES `fan_posts` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cached Fan Post translations by target language';