INSERT INTO heart_reward_settings (action_code, action_name, heart_amount, daily_limit, is_enabled)
SELECT 'quiz_create', '퀴즈 생성', 100, 20, 1
WHERE NOT EXISTS (
    SELECT 1 FROM heart_reward_settings WHERE action_code = 'quiz_create'
);
