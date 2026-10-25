CREATE TABLE `quiz_creation_histories` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_idx` bigint unsigned NOT NULL COMMENT 'Quiz creator',
  `quiz_idx` bigint unsigned NOT NULL COMMENT 'Created quiz ID; intentionally has no FK so quota history survives quiz deletion',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'UTC instant; daily quota uses Asia/Seoul calendar boundaries',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_quiz_creation_histories_quiz_idx` (`quiz_idx`),
  KEY `idx_quiz_creation_histories_member_created_at` (`member_idx`,`created_at`),
  CONSTRAINT `fk_quiz_creation_histories_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Persistent quiz creation events for daily quotas';

INSERT INTO `quiz_creation_histories` (`member_idx`, `quiz_idx`, `created_at`)
SELECT `member_idx`, `idx`, `created_at` FROM `quizzes`;

INSERT INTO `quiz_creation_histories` (`member_idx`, `quiz_idx`, `created_at`)
SELECT `history`.`member_idx`, CAST(SUBSTRING_INDEX(`history`.`operation_key`, ':', -1) AS UNSIGNED), `history`.`created_at`
FROM `heart_histories` AS `history`
WHERE `history`.`reason` = 'quiz_create_reward'
  AND `history`.`operation_key` REGEXP '^quiz_create:[0-9]+$'
  AND NOT EXISTS (
    SELECT 1 FROM `quiz_creation_histories` AS `creation`
    WHERE `creation`.`quiz_idx` = CAST(SUBSTRING_INDEX(`history`.`operation_key`, ':', -1) AS UNSIGNED)
  );
