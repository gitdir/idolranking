INSERT INTO heart_reward_settings (action_code, action_name, heart_amount, daily_limit, is_enabled)
VALUES
    ('watch_ads', 'Watch Ads', 50, 10, 1),
    ('invite_friend', 'Invite Friends', 1000, 0, 1)
ON DUPLICATE KEY UPDATE action_code = VALUES(action_code);
