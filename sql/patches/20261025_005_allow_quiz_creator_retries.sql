ALTER TABLE `quiz_attempts`
  DROP INDEX `uq_quiz_attempts_quiz_member_date`,
  ADD KEY `idx_quiz_attempts_quiz_member_date` (`quiz_idx`, `member_idx`, `attempt_date`, `idx`);
