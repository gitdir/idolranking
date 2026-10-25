-- Award weekly popular-quiz rewards to quiz creators and retain the ranked quiz for auditing.
-- Existing settlement rows remain intact with quiz_idx = NULL.
ALTER TABLE `quiz_weekly_settlement_rewards`
  DROP INDEX `uq_quiz_weekly_settlement_member`,
  ADD COLUMN `quiz_idx` bigint unsigned NULL
    COMMENT 'Ranked quiz; NULL for legacy member-ranking settlements or deleted quizzes'
    AFTER `member_idx`,
  ADD KEY `idx_quiz_weekly_settlement_rewards_quiz` (`quiz_idx`, `settlement_idx`),
  ADD CONSTRAINT `fk_quiz_weekly_settlement_rewards_quiz_idx`
    FOREIGN KEY (`quiz_idx`) REFERENCES `quizzes` (`idx`) ON DELETE SET NULL;