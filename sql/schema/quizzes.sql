CREATE TABLE `quizzes` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_idx` bigint unsigned NOT NULL COMMENT 'Quiz creator',
  `actor_member_idx` bigint unsigned NOT NULL COMMENT 'Authenticated creator',
  `title` varchar(255) NOT NULL COMMENT 'Quiz title',
  `idol_idx` bigint unsigned DEFAULT NULL COMMENT 'Selected active idol or group',
  `cover_image_path` varchar(1024) DEFAULT NULL COMMENT 'Optional managed quiz cover image',
  `question_count` tinyint unsigned NOT NULL,
  `quiz_version` int unsigned NOT NULL DEFAULT 1 COMMENT 'Current immutable question set version',
  `deleted_at` datetime(6) DEFAULT NULL COMMENT 'Soft deletion timestamp; quiz history remains intact',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  KEY `idx_quizzes_idol_idx_created_at_idx` (`idol_idx`,`created_at`,`idx`),
  KEY `idx_quizzes_member_idx_created_at_idx` (`member_idx`,`created_at`,`idx`),
  KEY `idx_quizzes_actor_member_idx_idx` (`actor_member_idx`,`idx`),
  KEY `idx_quizzes_deleted_at_created_at` (`deleted_at`,`created_at`,`idx`),
  CONSTRAINT `fk_quizzes_idol_idx` FOREIGN KEY (`idol_idx`) REFERENCES `idols` (`idx`),
  CONSTRAINT `fk_quizzes_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quizzes_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Member-created quizzes';

CREATE TABLE `quiz_likes` (
  `quiz_idx` bigint unsigned NOT NULL,
  `member_idx` bigint unsigned NOT NULL,
  `actor_member_idx` bigint unsigned DEFAULT NULL COMMENT 'Authenticated actor; differs when an Admin acts as a dummy member',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`quiz_idx`,`member_idx`),
  KEY `idx_quiz_likes_member_idx_quiz_idx` (`member_idx`,`quiz_idx`),
  CONSTRAINT `fk_quiz_likes_quiz_idx` FOREIGN KEY (`quiz_idx`) REFERENCES `quizzes` (`idx`),
  CONSTRAINT `fk_quiz_likes_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quiz_likes_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Member Likes on quizzes';

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

CREATE TABLE `quiz_questions` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quiz_idx` bigint unsigned NOT NULL,
  `quiz_version` int unsigned NOT NULL DEFAULT 1 COMMENT 'Question set version',
  `question_order` tinyint unsigned NOT NULL,
  `question_type` varchar(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'image, choice, or ox',
  `prompt` varchar(500) NOT NULL,
  `image_path` varchar(1024) DEFAULT NULL,
  `correct_choice_order` tinyint unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_quiz_questions_quiz_version_order` (`quiz_idx`,`quiz_version`,`question_order`),
  UNIQUE KEY `uq_quiz_questions_idx_quiz` (`idx`,`quiz_idx`),
  CONSTRAINT `fk_quiz_questions_quiz_idx` FOREIGN KEY (`quiz_idx`) REFERENCES `quizzes` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordered quiz questions and answer keys';

CREATE TABLE `quiz_question_choices` (
  `question_idx` bigint unsigned NOT NULL,
  `choice_order` tinyint unsigned NOT NULL,
  `choice_text` varchar(200) NOT NULL,
  PRIMARY KEY (`question_idx`,`choice_order`),
  CONSTRAINT `fk_quiz_question_choices_question_idx` FOREIGN KEY (`question_idx`) REFERENCES `quiz_questions` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordered question choices; OX stores O and X';

CREATE TABLE `quiz_attempts` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quiz_idx` bigint unsigned NOT NULL,
  `member_idx` bigint unsigned NOT NULL,
  `actor_member_idx` bigint unsigned NOT NULL,
  `attempt_date` date NOT NULL COMMENT 'Calendar date in app.timezone',
  `status` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'in_progress',
  `question_count` tinyint unsigned NOT NULL,
  `quiz_version` int unsigned NOT NULL DEFAULT 1 COMMENT 'Question set version captured at attempt start',
  `is_test_only` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Creator-owned test attempt, excluded from rewards and weekly rankings',
  `score` tinyint unsigned DEFAULT NULL,
  `is_perfect` tinyint(1) DEFAULT NULL,
  `awarded_hearts` bigint unsigned NOT NULL DEFAULT 0,
  `started_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `completed_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  KEY `idx_quiz_attempts_quiz_member_date` (`quiz_idx`,`member_idx`,`attempt_date`,`idx`),
  UNIQUE KEY `uq_quiz_attempts_idx_quiz` (`idx`,`quiz_idx`),
  KEY `idx_quiz_attempts_member_date_idx` (`member_idx`,`attempt_date`,`idx`),
  KEY `idx_quiz_attempts_actor_member_idx_idx` (`actor_member_idx`,`idx`),
  KEY `idx_quiz_attempts_status_completed_at` (`status`,`completed_at`),
  CONSTRAINT `fk_quiz_attempts_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quiz_attempts_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quiz_attempts_quiz_idx` FOREIGN KEY (`quiz_idx`) REFERENCES `quizzes` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily member attempts and creator-owned test retries';

CREATE TABLE `quiz_attempt_answers` (
  `attempt_idx` bigint unsigned NOT NULL,
  `quiz_idx` bigint unsigned NOT NULL,
  `question_idx` bigint unsigned NOT NULL,
  `question_order` tinyint unsigned NOT NULL,
  `selected_choice_order` tinyint unsigned NOT NULL,
  `is_correct` tinyint(1) DEFAULT NULL COMMENT 'Set only after completion',
  `submitted_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`attempt_idx`,`question_order`),
  UNIQUE KEY `uq_quiz_attempt_answers_attempt_question` (`attempt_idx`,`question_idx`),
  KEY `idx_quiz_attempt_answers_attempt_quiz` (`attempt_idx`,`quiz_idx`),
  KEY `idx_quiz_attempt_answers_question_quiz` (`question_idx`,`quiz_idx`),
  KEY `idx_quiz_attempt_answers_question_choice` (`question_idx`,`selected_choice_order`),
  CONSTRAINT `fk_quiz_attempt_answers_attempt_quiz` FOREIGN KEY (`attempt_idx`,`quiz_idx`) REFERENCES `quiz_attempts` (`idx`,`quiz_idx`),
  CONSTRAINT `fk_quiz_attempt_answers_question_quiz` FOREIGN KEY (`question_idx`,`quiz_idx`) REFERENCES `quiz_questions` (`idx`,`quiz_idx`),
  CONSTRAINT `fk_quiz_attempt_answers_choice` FOREIGN KEY (`question_idx`,`selected_choice_order`) REFERENCES `quiz_question_choices` (`question_idx`,`choice_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable submitted quiz answers';

CREATE TABLE `quiz_weekly_settlements` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `week_start_date` date NOT NULL COMMENT 'Monday in Asia/Seoul',
  `week_end_date` date NOT NULL COMMENT 'Exclusive next Monday in Asia/Seoul',
  `settled_at` datetime(6) DEFAULT NULL COMMENT 'UTC settlement completion time; NULL only while the transaction is open',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_quiz_weekly_settlements_week_start` (`week_start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Idempotent weekly Quiz ranking settlement headers';

CREATE TABLE `quiz_weekly_settlement_rewards` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `settlement_idx` bigint unsigned NOT NULL,
  `rank_position` tinyint unsigned NOT NULL,
  `member_idx` bigint unsigned NOT NULL,
  `quiz_idx` bigint unsigned NULL COMMENT 'Ranked quiz; NULL for legacy member-ranking settlements or deleted quizzes',
  `participation_count` bigint unsigned NOT NULL,
  `reached_at` datetime(6) NOT NULL COMMENT 'UTC time when the member reached their final weekly count',
  `reward_amount` bigint unsigned NOT NULL,
  `operation_key` varchar(191) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_quiz_weekly_settlement_rank` (`settlement_idx`,`rank_position`),
  UNIQUE KEY `uq_quiz_weekly_settlement_operation` (`operation_key`),
  KEY `idx_quiz_weekly_settlement_rewards_member` (`member_idx`,`settlement_idx`),
  KEY `idx_quiz_weekly_settlement_rewards_quiz` (`quiz_idx`,`settlement_idx`),
  CONSTRAINT `fk_quiz_weekly_settlement_rewards_settlement_idx` FOREIGN KEY (`settlement_idx`) REFERENCES `quiz_weekly_settlements` (`idx`),
  CONSTRAINT `fk_quiz_weekly_settlement_rewards_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quiz_weekly_settlement_rewards_quiz_idx` FOREIGN KEY (`quiz_idx`) REFERENCES `quizzes` (`idx`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditable occupied Quiz ranking rewards by week';
