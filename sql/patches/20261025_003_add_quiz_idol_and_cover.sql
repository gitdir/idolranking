ALTER TABLE `quizzes`
  ADD COLUMN `idol_idx` bigint unsigned DEFAULT NULL COMMENT 'Selected active idol or group' AFTER `actor_member_idx`,
  ADD COLUMN `cover_image_path` varchar(1024) DEFAULT NULL COMMENT 'Optional managed quiz cover image' AFTER `idol_idx`,
  ADD KEY `idx_quizzes_idol_idx_created_at_idx` (`idol_idx`,`created_at`,`idx`),
  ADD CONSTRAINT `fk_quizzes_idol_idx` FOREIGN KEY (`idol_idx`) REFERENCES `idols` (`idx`);
