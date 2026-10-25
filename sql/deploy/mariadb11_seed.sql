-- Essential application configuration only. No member or activity data.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO heart_reward_settings (action_code, action_name, heart_amount, daily_limit, is_enabled) VALUES
('comment_create', '댓글 작성', 10, 20, 1),
('daily_attendance', '일일 출석', 100, 1, 1),
('fan_post_create', '팬포스트 작성', 30, 20, 1),
('quiz_creator_reward_1', '퀴즈 제작자 보상 (1문항)', 3, 0, 1),
('quiz_creator_reward_2', '퀴즈 제작자 보상 (2문항)', 5, 0, 1),
('quiz_creator_reward_3', '퀴즈 제작자 보상 (3문항)', 10, 0, 1),
('quiz_weekly_rank_1', '주간 퀴즈 참여 순위 1위', 1000, 0, 1),
('quiz_weekly_rank_2', '주간 퀴즈 참여 순위 2위', 500, 0, 1),
('quiz_weekly_rank_3', '주간 퀴즈 참여 순위 3위', 100, 0, 1),
('fan_post_recommend_received', '팬포스트 추천 받음', 1, 100, 1),
('idol_registration_approved', '아이돌/그룹 등록 승인', 3000, 0, 1),
('invite_friend', '친구 초대 성공', 1000, 0, 1),
('profile_complete', '프로필 완성', 100, 0, 1),
('signup', '가입 완료', 1000, 0, 1),
('watch_ads', 'Watch Ads', 50, 10, 0);

-- Existing fixed reward actions remain unchanged; Daily Check-in starts with its current 100-Heart fixed range.
UPDATE heart_reward_settings
SET min_heart_amount = heart_amount, max_heart_amount = heart_amount
WHERE action_code = 'daily_attendance';

INSERT INTO heart_reward_settings (action_code, action_name, heart_amount, min_heart_amount, max_heart_amount, daily_limit, is_enabled)
SELECT 'quiz_perfect', 'Quiz Perfect Score', 20, 10, 30, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM heart_reward_settings WHERE action_code = 'quiz_perfect');
