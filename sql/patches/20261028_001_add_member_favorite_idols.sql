-- Add optional favorites for existing members. Existing rows remain unchanged.
ALTER TABLE `members`
  ADD COLUMN `favorite_idol_1_idx` BIGINT UNSIGNED NULL COMMENT 'Member favorite idol or group, priority 1' AFTER `is_dummy`,
  ADD COLUMN `favorite_idol_2_idx` BIGINT UNSIGNED NULL COMMENT 'Member favorite idol or group, priority 2' AFTER `favorite_idol_1_idx`,
  ADD COLUMN `favorite_idol_3_idx` BIGINT UNSIGNED NULL COMMENT 'Member favorite idol or group, priority 3' AFTER `favorite_idol_2_idx`,
  ADD KEY `idx_members_favorite_idol_1_idx` (`favorite_idol_1_idx`),
  ADD KEY `idx_members_favorite_idol_2_idx` (`favorite_idol_2_idx`),
  ADD KEY `idx_members_favorite_idol_3_idx` (`favorite_idol_3_idx`),
  ADD CONSTRAINT `fk_members_favorite_idol_1_idx` FOREIGN KEY (`favorite_idol_1_idx`) REFERENCES `idols` (`idx`),
  ADD CONSTRAINT `fk_members_favorite_idol_2_idx` FOREIGN KEY (`favorite_idol_2_idx`) REFERENCES `idols` (`idx`),
  ADD CONSTRAINT `fk_members_favorite_idol_3_idx` FOREIGN KEY (`favorite_idol_3_idx`) REFERENCES `idols` (`idx`);
