-- Remove the obsolete Quiz creation reward setting. Existing Heart history records are preserved.
DELETE FROM heart_reward_settings WHERE action_code = 'quiz_create';