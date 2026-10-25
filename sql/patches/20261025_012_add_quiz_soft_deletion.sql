ALTER TABLE `quizzes`
  ADD COLUMN `deleted_at` datetime(6) DEFAULT NULL
    COMMENT 'Soft deletion timestamp; quiz history remains intact'
    AFTER `quiz_version`,
  ADD KEY `idx_quizzes_deleted_at_created_at` (`deleted_at`, `created_at`, `idx`);
