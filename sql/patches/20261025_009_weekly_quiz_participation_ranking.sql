ALTER TABLE `quiz_attempts`
  ADD COLUMN `is_test_only` tinyint(1) NOT NULL DEFAULT 0
    COMMENT 'Creator-owned test attempt, excluded from rewards and weekly rankings'
    AFTER `quiz_version`,
  ADD KEY `idx_quiz_attempts_status_completed_at` (`status`, `completed_at`);

UPDATE `quiz_attempts` AS `attempt`
JOIN `quizzes` AS `quiz` ON `quiz`.`idx` = `attempt`.`quiz_idx`
SET `attempt`.`is_test_only` = 1
WHERE `attempt`.`member_idx` = `quiz`.`member_idx`;

CREATE TABLE `quiz_weekly_settlements` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `week_start_date` date NOT NULL COMMENT 'Monday in Asia/Seoul',
  `week_end_date` date NOT NULL COMMENT 'Exclusive next Monday in Asia/Seoul',
  `settled_at` datetime(6) DEFAULT NULL COMMENT 'UTC settlement completion time',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_quiz_weekly_settlements_week_start` (`week_start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Idempotent weekly Quiz ranking settlement headers';

CREATE TABLE `quiz_weekly_settlement_rewards` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `settlement_idx` bigint unsigned NOT NULL,
  `rank_position` tinyint unsigned NOT NULL,
  `member_idx` bigint unsigned NOT NULL,
  `participation_count` bigint unsigned NOT NULL,
  `reached_at` datetime(6) NOT NULL COMMENT 'UTC time when the member reached their final weekly count',
  `reward_amount` bigint unsigned NOT NULL,
  `operation_key` varchar(191) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_quiz_weekly_settlement_rank` (`settlement_idx`, `rank_position`),
  UNIQUE KEY `uq_quiz_weekly_settlement_member` (`settlement_idx`, `member_idx`),
  UNIQUE KEY `uq_quiz_weekly_settlement_operation` (`operation_key`),
  KEY `idx_quiz_weekly_settlement_rewards_member` (`member_idx`, `settlement_idx`),
  CONSTRAINT `fk_quiz_weekly_settlement_rewards_settlement_idx`
    FOREIGN KEY (`settlement_idx`) REFERENCES `quiz_weekly_settlements` (`idx`),
  CONSTRAINT `fk_quiz_weekly_settlement_rewards_member_idx`
    FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Auditable occupied Quiz ranking rewards by week';

INSERT INTO `heart_reward_settings` (`action_code`, `action_name`, `heart_amount`, `daily_limit`, `is_enabled`)
SELECT 'quiz_weekly_rank_1', '주간 퀴즈 참여 순위 1위', 1000, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM `heart_reward_settings` WHERE `action_code` = 'quiz_weekly_rank_1');

INSERT INTO `heart_reward_settings` (`action_code`, `action_name`, `heart_amount`, `daily_limit`, `is_enabled`)
SELECT 'quiz_weekly_rank_2', '주간 퀴즈 참여 순위 2위', 500, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM `heart_reward_settings` WHERE `action_code` = 'quiz_weekly_rank_2');

INSERT INTO `heart_reward_settings` (`action_code`, `action_name`, `heart_amount`, `daily_limit`, `is_enabled`)
SELECT 'quiz_weekly_rank_3', '주간 퀴즈 참여 순위 3위', 100, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM `heart_reward_settings` WHERE `action_code` = 'quiz_weekly_rank_3');
