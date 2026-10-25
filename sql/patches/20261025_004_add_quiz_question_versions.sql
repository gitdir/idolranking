ALTER TABLE `quizzes`
  ADD COLUMN `quiz_version` int unsigned NOT NULL DEFAULT 1 COMMENT 'Current immutable question set version' AFTER `question_count`;

ALTER TABLE `quiz_questions`
  DROP INDEX `uq_quiz_questions_quiz_order`,
  ADD COLUMN `quiz_version` int unsigned NOT NULL DEFAULT 1 COMMENT 'Question set version' AFTER `quiz_idx`,
  ADD UNIQUE KEY `uq_quiz_questions_quiz_version_order` (`quiz_idx`, `quiz_version`, `question_order`);

ALTER TABLE `quiz_attempts`
  ADD COLUMN `quiz_version` int unsigned NOT NULL DEFAULT 1 COMMENT 'Question set version captured at attempt start' AFTER `question_count`;
