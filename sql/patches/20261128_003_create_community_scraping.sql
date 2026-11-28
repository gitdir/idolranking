CREATE TABLE `scrape_sources` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `listing_url` varchar(2048) NOT NULL,
  `post_link_selector` varchar(255) NOT NULL,
  `title_selector` varchar(255) NOT NULL,
  `content_selector` varchar(255) NOT NULL,
  `image_selector` varchar(255) DEFAULT NULL,
  `video_selector` varchar(255) DEFAULT NULL,
  `is_enabled` tinyint unsigned NOT NULL DEFAULT 0,
  `redistribution_permitted` tinyint unsigned NOT NULL DEFAULT 0,
  `attribution_required` tinyint unsigned NOT NULL DEFAULT 1,
  `deleted_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL,
  PRIMARY KEY (`idx`),
  KEY `idx_scrape_sources_enabled_deleted` (`is_enabled`,`deleted_at`,`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `scrape_days` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source_idx` bigint unsigned NOT NULL,
  `collect_date` date NOT NULL COMMENT 'Asia/Seoul date',
  `scheduled_at` datetime(6) NOT NULL COMMENT 'UTC timestamp',
  `finished_at` datetime(6) DEFAULT NULL,
  `fan_post_idx` bigint unsigned DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `failure_reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_scrape_days_source_date` (`source_idx`,`collect_date`),
  KEY `idx_scrape_days_scheduled` (`status`,`scheduled_at`),
  CONSTRAINT `fk_scrape_days_source_idx` FOREIGN KEY (`source_idx`) REFERENCES `scrape_sources` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `scrape_imports` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source_idx` bigint unsigned NOT NULL,
  `original_url` varchar(2048) NOT NULL,
  `normalized_identifier` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `collected_at` datetime(6) NOT NULL,
  `status` varchar(16) NOT NULL,
  `fan_post_idx` bigint unsigned DEFAULT NULL,
  `dummy_member_idx` bigint unsigned DEFAULT NULL,
  `failure_reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_scrape_imports_identifier` (`normalized_identifier`),
  KEY `idx_scrape_imports_source_collected` (`source_idx`,`collected_at`),
  CONSTRAINT `fk_scrape_imports_source_idx` FOREIGN KEY (`source_idx`) REFERENCES `scrape_sources` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
