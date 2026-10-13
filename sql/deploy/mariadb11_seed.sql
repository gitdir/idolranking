-- Essential application configuration only. No member or activity data.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO heart_reward_settings (action_code, action_name, heart_amount, daily_limit, is_enabled) VALUES
('comment_create', '댓글 작성', 10, 20, 1),
('daily_attendance', '일일 출석', 100, 1, 1),
('fan_post_create', '팬포스트 작성', 30, 20, 1),
('fan_post_recommend_received', '팬포스트 추천 받음', 1, 100, 1),
('idol_registration_approved', '아이돌/그룹 등록 승인', 3000, 0, 1),
('invite_friend', '친구 초대 성공', 1000, 0, 1),
('profile_complete', '프로필 완성', 100, 0, 1),
('signup', '가입 완료', 1000, 0, 1),
('watch_ads', 'Watch Ads', 50, 10, 0);
