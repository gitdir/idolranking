UPDATE `heart_reward_settings`
SET `is_enabled` = 0, `updated_at` = CURRENT_TIMESTAMP(6)
WHERE `action_code` = 'quiz_create';

INSERT INTO `heart_reward_settings`
  (`action_code`, `action_name`, `heart_amount`, `daily_limit`, `is_enabled`)
SELECT 'quiz_creator_reward_1', '퀴즈 제작자 보상 (1문항)', 3, 0, 1
WHERE NOT EXISTS (
  SELECT 1 FROM `heart_reward_settings` WHERE `action_code` = 'quiz_creator_reward_1'
);

INSERT INTO `heart_reward_settings`
  (`action_code`, `action_name`, `heart_amount`, `daily_limit`, `is_enabled`)
SELECT 'quiz_creator_reward_2', '퀴즈 제작자 보상 (2문항)', 5, 0, 1
WHERE NOT EXISTS (
  SELECT 1 FROM `heart_reward_settings` WHERE `action_code` = 'quiz_creator_reward_2'
);

INSERT INTO `heart_reward_settings`
  (`action_code`, `action_name`, `heart_amount`, `daily_limit`, `is_enabled`)
SELECT 'quiz_creator_reward_3', '퀴즈 제작자 보상 (3문항)', 10, 0, 1
WHERE NOT EXISTS (
  SELECT 1 FROM `heart_reward_settings` WHERE `action_code` = 'quiz_creator_reward_3'
);
