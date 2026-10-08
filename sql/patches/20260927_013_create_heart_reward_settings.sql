CREATE TABLE heart_reward_settings (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    action_name VARCHAR(100) NOT NULL,
    heart_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    daily_limit INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 means unlimited; fixed one-time rules use operation_key',
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx), UNIQUE KEY uq_heart_reward_settings_action_code (action_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO heart_reward_settings (action_code, action_name, heart_amount, daily_limit, is_enabled) VALUES
('signup', '가입 완료', 1000, 0, 1), ('profile_complete', '프로필 완성', 100, 0, 1),
('daily_attendance', '일일 출석', 100, 1, 1), ('fan_post_create', '팬포스트 작성', 30, 20, 1),
('comment_create', '댓글 작성', 10, 20, 1), ('fan_post_recommend_received', '팬포스트 추천 받음', 1, 100, 1),
('idol_registration_approved', '아이돌/그룹 등록 승인', 3000, 0, 1);
