CREATE TABLE `quizzes` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_idx` bigint unsigned NOT NULL,
  `actor_member_idx` bigint unsigned NOT NULL,
  `question_count` tinyint unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  KEY `idx_quizzes_member_idx_created_at_idx` (`member_idx`,`created_at`,`idx`),
  KEY `idx_quizzes_actor_member_idx_idx` (`actor_member_idx`,`idx`),
  CONSTRAINT `fk_quizzes_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quizzes_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `quiz_questions` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quiz_idx` bigint unsigned NOT NULL,
  `question_order` tinyint unsigned NOT NULL,
  `question_type` varchar(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `prompt` varchar(500) NOT NULL,
  `image_path` varchar(1024) DEFAULT NULL,
  `correct_choice_order` tinyint unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_quiz_questions_quiz_order` (`quiz_idx`,`question_order`),
  UNIQUE KEY `uq_quiz_questions_idx_quiz` (`idx`,`quiz_idx`),
  CONSTRAINT `fk_quiz_questions_quiz_idx` FOREIGN KEY (`quiz_idx`) REFERENCES `quizzes` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `quiz_question_choices` (
  `question_idx` bigint unsigned NOT NULL,
  `choice_order` tinyint unsigned NOT NULL,
  `choice_text` varchar(200) NOT NULL,
  PRIMARY KEY (`question_idx`,`choice_order`),
  CONSTRAINT `fk_quiz_question_choices_question_idx` FOREIGN KEY (`question_idx`) REFERENCES `quiz_questions` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `quiz_attempts` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quiz_idx` bigint unsigned NOT NULL,
  `member_idx` bigint unsigned NOT NULL,
  `actor_member_idx` bigint unsigned NOT NULL,
  `attempt_date` date NOT NULL COMMENT 'Calendar date in app.timezone',
  `status` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'in_progress',
  `question_count` tinyint unsigned NOT NULL,
  `score` tinyint unsigned DEFAULT NULL,
  `is_perfect` tinyint(1) DEFAULT NULL,
  `awarded_hearts` bigint unsigned NOT NULL DEFAULT 0,
  `started_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `completed_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_quiz_attempts_quiz_member_date` (`quiz_idx`,`member_idx`,`attempt_date`),
  UNIQUE KEY `uq_quiz_attempts_idx_quiz` (`idx`,`quiz_idx`),
  KEY `idx_quiz_attempts_member_date_idx` (`member_idx`,`attempt_date`,`idx`),
  KEY `idx_quiz_attempts_actor_member_idx_idx` (`actor_member_idx`,`idx`),
  CONSTRAINT `fk_quiz_attempts_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quiz_attempts_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quiz_attempts_quiz_idx` FOREIGN KEY (`quiz_idx`) REFERENCES `quizzes` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `quiz_attempt_answers` (
  `attempt_idx` bigint unsigned NOT NULL,
  `quiz_idx` bigint unsigned NOT NULL,
  `question_idx` bigint unsigned NOT NULL,
  `question_order` tinyint unsigned NOT NULL,
  `selected_choice_order` tinyint unsigned NOT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `submitted_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`attempt_idx`,`question_order`),
  UNIQUE KEY `uq_quiz_attempt_answers_attempt_question` (`attempt_idx`,`question_idx`),
  KEY `idx_quiz_attempt_answers_attempt_quiz` (`attempt_idx`,`quiz_idx`),
  KEY `idx_quiz_attempt_answers_question_quiz` (`question_idx`,`quiz_idx`),
  KEY `idx_quiz_attempt_answers_question_choice` (`question_idx`,`selected_choice_order`),
  CONSTRAINT `fk_quiz_attempt_answers_attempt_quiz` FOREIGN KEY (`attempt_idx`,`quiz_idx`) REFERENCES `quiz_attempts` (`idx`,`quiz_idx`),
  CONSTRAINT `fk_quiz_attempt_answers_question_quiz` FOREIGN KEY (`question_idx`,`quiz_idx`) REFERENCES `quiz_questions` (`idx`,`quiz_idx`),
  CONSTRAINT `fk_quiz_attempt_answers_choice` FOREIGN KEY (`question_idx`,`selected_choice_order`) REFERENCES `quiz_question_choices` (`question_idx`,`choice_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO heart_reward_settings (action_code, action_name, heart_amount, min_heart_amount, max_heart_amount, daily_limit, is_enabled)
SELECT 'quiz_perfect', 'Quiz Perfect Score', 20, 10, 30, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM heart_reward_settings WHERE action_code = 'quiz_perfect');
