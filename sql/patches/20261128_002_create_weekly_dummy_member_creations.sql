CREATE TABLE `weekly_dummy_member_creations` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `week_start_date` date NOT NULL COMMENT 'Monday in Asia/Seoul',
  `member_idx` bigint unsigned DEFAULT NULL COMMENT 'Created operator-managed dummy member',
  `created_at` datetime(6) NOT NULL,
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_weekly_dummy_member_creations_week` (`week_start_date`),
  UNIQUE KEY `uq_weekly_dummy_member_creations_member` (`member_idx`),
  CONSTRAINT `fk_weekly_dummy_member_creations_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One audited automatic dummy creation per Seoul week';
