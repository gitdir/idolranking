INSERT INTO heart_reward_settings (action_code, action_name, heart_amount, daily_limit, is_enabled)
VALUES ('invite_friend', '친구 초대 성공', 1000, 0, 1)
ON DUPLICATE KEY UPDATE action_code = VALUES(action_code);
