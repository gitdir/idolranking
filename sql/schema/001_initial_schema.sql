-- IdolRanking 1차 초기 스키마 / MySQL 8.4
-- 대상 DB를 선택한 상태에서 빈 스키마에 한 번 적용하는 파일이다.
-- CREATE DATABASE, USE, 데이터 입력, 기존 테이블 삭제는 포함하지 않는다.
-- DDL은 암묵적으로 커밋되므로 이 파일 전체가 하나의 트랜잭션은 아니다.
-- 모든 DATETIME은 애플리케이션/연결 시간대를 UTC로 맞춰 사용한다.
-- updated_at은 애플리케이션에서 변경한다. 조회수 증가만으로 갱신하지 않는다.
-- FK는 ON DELETE/ON UPDATE 절을 생략하여 기본 NO ACTION(RESTRICT)을 사용한다.
-- FK 검사 비활성화, CASCADE, 트리거 및 저장 프로시저는 사용하지 않는다.

CREATE TABLE members (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '회원 ID',
    member_code VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Public immutable member identifier',
    email VARCHAR(254) NULL COMMENT '이메일 로그인 주소; 탈퇴 시 NULL',
    password_hash VARCHAR(255) NULL COMMENT '비밀번호 해시; Google 전용 또는 탈퇴 회원은 NULL',
    nickname VARCHAR(50) NULL COMMENT '공개 닉네임; 탈퇴 시 NULL',
    member_level SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0 탈퇴, 1 일반, 10 관리자; 중간 권한은 애플리케이션 정책으로 추가',
    is_dummy TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 일반 계정, 1 관리자 생성 더미계정',
    favorite_idol_1_idx BIGINT UNSIGNED NULL COMMENT 'Member favorite idol or group, priority 1',
    favorite_idol_2_idx BIGINT UNSIGNED NULL COMMENT 'Member favorite idol or group, priority 2',
    favorite_idol_3_idx BIGINT UNSIGNED NULL COMMENT 'Member favorite idol or group, priority 3',
    status VARCHAR(16) NOT NULL DEFAULT 'active' COMMENT 'active, suspended, inactive',
    country_code CHAR(2) NULL COMMENT '사용자 선택 ISO 3166-1 alpha-2 대문자 코드; 탈퇴 시 NULL 가능',
    profile_image_path VARCHAR(1024) NULL COMMENT '회원 프로필 이미지 공개 저장 경로',
    heart_balance BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '현재 하트 잔액; 하트 이력 합계와 일치해야 함',
    email_verified_at DATETIME(6) NULL COMMENT '현재 이메일 확인 완료 시각',
    last_login_at DATETIME(6) NULL COMMENT '최근 정상 로그인 시각',
    password_changed_at DATETIME(6) NULL COMMENT '최초 비밀번호 설정 또는 최근 변경 시각',
    created_by_member_idx BIGINT UNSIGNED NULL COMMENT '더미계정을 생성한 관리자; 일반 가입은 NULL',
    deleted_at DATETIME(6) NULL COMMENT '탈퇴 시각',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '가입 시각',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '최근 수정 시각',
    PRIMARY KEY (idx),
    UNIQUE KEY uq_members_member_code (member_code),
    UNIQUE KEY uq_members_email (email),
    UNIQUE KEY uq_members_nickname (nickname),
    KEY idx_members_is_dummy_member_level_status_idx (is_dummy, member_level, status, idx),
    KEY idx_members_favorite_idol_1_idx (favorite_idol_1_idx),
    KEY idx_members_favorite_idol_2_idx (favorite_idol_2_idx),
    KEY idx_members_favorite_idol_3_idx (favorite_idol_3_idx),
    KEY idx_members_created_by_member_idx (created_by_member_idx),
    KEY idx_members_country_code_idx (country_code, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='회원, 권한, 국가 및 현재 하트 잔액';

CREATE TABLE member_social_accounts (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '소셜 연결 ID',
    member_idx BIGINT UNSIGNED NOT NULL COMMENT '연결 회원 ID; 더미 연결 금지는 애플리케이션에서 검사',
    provider VARCHAR(16) NOT NULL COMMENT '현재 google만 허용',
    provider_user_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Google sub; 이메일이 아닌 대소문자 구분 외부 식별값',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '연결 시각',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '최근 수정 시각',
    PRIMARY KEY (idx),
    UNIQUE KEY uq_social_provider_user (provider, provider_user_id),
    UNIQUE KEY uq_social_member_idx_provider (member_idx, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='회원별 Google 계정 연결; 인증 토큰 원문 저장 금지';

CREATE TABLE member_login_histories (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '로그인 이력 ID',
    member_idx BIGINT UNSIGNED NULL COMMENT '식별한 회원 ID; 미등록 계정 등 식별 불가 실패는 NULL',
    login_type VARCHAR(16) NOT NULL COMMENT 'password 또는 google',
    result VARCHAR(16) NOT NULL COMMENT 'success 또는 failed',
    ip_address VARCHAR(45) NULL COMMENT '신뢰된 프록시 정책으로 확인한 IPv4/IPv6; 확인 불가 시 NULL',
    user_agent VARCHAR(1024) NULL COMMENT '길이를 제한한 User-Agent; 비밀번호/토큰/세션 ID 저장 금지',
    failure_reason VARCHAR(64) NULL COMMENT '실패 내부 코드; 성공 시 NULL, 상세 오류 원문 금지',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '로그인 시도 결과 기록 시각',
    PRIMARY KEY (idx),
    KEY idx_login_member_idx_created_at_idx (member_idx, created_at, idx),
    KEY idx_login_ip_address_created_at_idx (ip_address, created_at, idx),
    KEY idx_login_created_at_idx (created_at, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='일반회원/관리자/더미의 직접 로그인 성공 및 실패 이력';

CREATE TABLE password_reset_tokens (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '비밀번호 재설정 요청 ID',
    member_idx BIGINT UNSIGNED NOT NULL COMMENT '재설정 대상 회원 ID',
    token_hash BINARY(32) NOT NULL COMMENT '무작위 재설정 토큰의 SHA-256 바이너리 해시; 원문 저장 금지',
    expires_at DATETIME(6) NOT NULL COMMENT '만료 시각; 재발급/회원정보 변경 시 앞당겨 무효화 가능',
    used_at DATETIME(6) NULL COMMENT '실제 비밀번호 변경 완료 시각; 미사용은 NULL',
    requested_ip VARCHAR(45) NULL COMMENT '요청 IPv4/IPv6; 확인 불가 시 NULL',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '발급 시각',
    PRIMARY KEY (idx),
    UNIQUE KEY uq_password_reset_hash (token_hash),
    KEY idx_password_reset_member_idx_used_at_expires_at (member_idx, used_at, expires_at),
    KEY idx_password_reset_expires_at_idx (expires_at, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='만료시간이 있는 일회용 비밀번호 재설정 토큰 해시';

CREATE TABLE idols (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '개인 또는 그룹 ID',
    type VARCHAR(16) NOT NULL COMMENT 'individual 개인, group 그룹',
    gender VARCHAR(8) NOT NULL COMMENT 'male 남자/남자그룹, female 여자/여자그룹',
    name VARCHAR(200) NOT NULL COMMENT '공식 표시 이름; 동명이인 허용',
    english_name VARCHAR(200) NULL COMMENT '공식 영문 표시 이름',
    agency VARCHAR(200) NULL COMMENT '소속사',
    birth_date DATE NULL COMMENT '개인 생년월일; 그룹은 NULL',
    height_cm DECIMAL(5,2) NULL COMMENT '개인 키(cm); 그룹은 NULL',
    weight_kg DECIMAL(5,2) NULL COMMENT '개인 몸무게(kg); 그룹은 NULL',
    blood_type VARCHAR(8) NULL COMMENT '개인 혈액형; 그룹은 NULL',
    position VARCHAR(200) NULL COMMENT '개인 포지션; 그룹은 NULL',
    genre VARCHAR(200) NULL COMMENT '그룹 장르; 개인은 NULL',
    leader VARCHAR(200) NULL COMMENT '그룹 리더 이름; 자유 입력, 개인은 NULL',
    sns VARCHAR(1000) NULL COMMENT 'Legacy single SNS URL or handle',
    sns_links TEXT NULL COMMENT 'JSON array of up to ten validated public SNS URLs',
    image_path VARCHAR(1024) NULL COMMENT '대표 프로필 이미지 경로',
    content TEXT NULL COMMENT '공개 짧은 소개',
    status VARCHAR(16) NOT NULL DEFAULT 'active' COMMENT 'active 또는 inactive',
    view_count BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '누적 조회수; 랭킹 점수와 무관',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '등록 승인으로 생성된 시각',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '정보 수정 시각; 조회수 증가 시 유지',
    PRIMARY KEY (idx),
    KEY idx_idols_type_gender_status_idx (type, gender, status, idx),
    KEY idx_idols_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='개인 아이돌 및 그룹; 생성 경로는 등록 신청 승인으로 통일';

CREATE TABLE idol_localizations (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idol_idx BIGINT UNSIGNED NOT NULL,
    language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    localized_name VARCHAR(200) NULL,
    description TEXT NULL,
    agency VARCHAR(200) NULL,
    position VARCHAR(200) NULL,
    genre VARCHAR(200) NULL,
    leader VARCHAR(200) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_idol_localizations_idol_language (idol_idx, language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Reviewed localized names and descriptions for idols and groups';
CREATE TABLE idol_debuts (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idol_idx BIGINT UNSIGNED NOT NULL,
    country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    debut_date DATE NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_idol_debuts_idol_country (idol_idx, country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Country-specific idol and group debut dates';

CREATE TABLE idol_name_aliases (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idol_idx BIGINT UNSIGNED NOT NULL,
    language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    alias_name VARCHAR(200) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    KEY idx_idol_name_aliases_idol_language (idol_idx, language_code),
    KEY idx_idol_name_aliases_language_name (language_code, alias_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Additional language-tagged idol and group names';

CREATE TABLE idol_group_members (
    individual_idx BIGINT UNSIGNED NOT NULL COMMENT 'type=individual인 idols ID',
    group_idx BIGINT UNSIGNED NOT NULL COMMENT 'type=group인 idols ID',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '소속 연결 시각',
    PRIMARY KEY (individual_idx, group_idx),
    KEY idx_group_members_group_idx_individual_idx (group_idx, individual_idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='개인과 그룹의 다대다 소속; 유형/성별 일치는 애플리케이션 검사';

CREATE TABLE idol_votes (
    actor_member_idx BIGINT UNSIGNED NULL COMMENT 'Actual authenticated actor; differs when an Admin acts as a dummy member',
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '완료된 투표 ID',
    member_idx BIGINT UNSIGNED NOT NULL COMMENT '직접 로그인해 하트를 사용한 회원',
    idol_idx BIGINT UNSIGNED NOT NULL COMMENT '투표 대상 개인 또는 그룹',
    heart_amount BIGINT UNSIGNED NOT NULL COMMENT '실제 사용한 하트 수; 랭킹은 대상별 합계',
    country_code_at_vote CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NULL,
    request_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '회원별 동일 투표 요청 재전송 식별키',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '투표 완료 시각',
    PRIMARY KEY (idx),
    UNIQUE KEY uq_votes_member_idx_request_key (member_idx, request_key),
    KEY idx_votes_idol_idx_created_at_idx (idol_idx, created_at, idx),
    KEY idx_votes_member_idx_created_at_idx (member_idx, created_at, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='수정/삭제하지 않는 완료 투표; 잔액 차감 및 이력과 원자적으로 생성';

CREATE TABLE heart_histories (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '하트 증감 이력 ID',
    member_idx BIGINT UNSIGNED NOT NULL COMMENT '하트 잔액 대상 회원',
    amount BIGINT NOT NULL COMMENT '양수 지급, 음수 차감, 0은 활성화된 0하트 액션 기록',
    balance_after BIGINT UNSIGNED NOT NULL COMMENT '해당 처리 직후 잔액',
    reason VARCHAR(32) NOT NULL COMMENT 'Configured rewards, vote spending, admin adjustment, or reward reversal',
    vote_idx BIGINT UNSIGNED NULL COMMENT 'vote일 때만 연결되는 투표 ID',
    comment_idx BIGINT UNSIGNED NULL COMMENT '댓글 보상 및 보상 회수 대상 댓글 ID',
    reversal_of_history_idx BIGINT UNSIGNED NULL COMMENT '전액 회수 대상 원본 댓글 보상 이력 ID; 원본당 1회 회수',
    operation_key VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '회원별 지급/차감 사건의 고정 중복 방지 키',
    actor_member_idx BIGINT UNSIGNED NULL COMMENT '실제 작업자; 시스템 지급/회수는 NULL',
    memo VARCHAR(1000) NULL COMMENT '관리자 조정 또는 보상 회수 사유',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '증감 처리 시각',
    PRIMARY KEY (idx),
    UNIQUE KEY uq_hearts_vote_idx (vote_idx),
    UNIQUE KEY uq_hearts_member_idx_operation_key (member_idx, operation_key),
    UNIQUE KEY uq_hearts_reversal_of_history_idx (reversal_of_history_idx),
    KEY idx_hearts_member_idx_idx (member_idx, idx),
    KEY idx_hearts_member_idx_created_at_idx (member_idx, created_at, idx),
    KEY idx_hearts_member_idx_reason_created_at_idx (member_idx, reason, created_at, idx),
    KEY idx_hearts_actor_member_idx_idx (actor_member_idx, idx),
    KEY idx_hearts_comment_idx (comment_idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='추가 기록 방식의 하트 원장; 기존 이력 변경/삭제는 애플리케이션에서 금지';

CREATE TABLE heart_reward_settings (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    action_name VARCHAR(100) NOT NULL,
    heart_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    min_heart_amount BIGINT UNSIGNED NULL,
    max_heart_amount BIGINT UNSIGNED NULL,
    daily_limit INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 means unlimited; fixed one-time rules use operation_key',
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_heart_reward_settings_action_code (action_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO heart_reward_settings (action_code, action_name, heart_amount, daily_limit, is_enabled) VALUES
('signup', '가입 완료', 1000, 0, 1), ('profile_complete', '프로필 완성', 100, 0, 1),
('daily_attendance', '일일 출석', 100, 1, 1), ('fan_post_create', '팬포스트 작성', 30, 20, 1),
('comment_create', '댓글 작성', 10, 20, 1), ('fan_post_recommend_received', '팬포스트 추천 받음', 1, 100, 1),
('idol_registration_approved', '아이돌/그룹 등록 승인', 3000, 0, 1);

INSERT INTO heart_reward_settings (action_code, action_name, heart_amount, daily_limit, is_enabled) VALUES
('watch_ads', 'Watch Ads', 50, 10, 1),
('invite_friend', 'Invite Friends', 1000, 0, 1);

UPDATE heart_reward_settings
SET min_heart_amount = heart_amount, max_heart_amount = heart_amount
WHERE action_code = 'daily_attendance';

INSERT INTO heart_reward_settings (action_code, action_name, heart_amount, daily_limit, is_enabled) VALUES
('quiz_creator_reward_1', '퀴즈 제작자 보상 (1문항)', 3, 0, 1),
('quiz_creator_reward_2', '퀴즈 제작자 보상 (2문항)', 5, 0, 1),
('quiz_creator_reward_3', '퀴즈 제작자 보상 (3문항)', 10, 0, 1);

INSERT INTO heart_reward_settings (action_code, action_name, heart_amount, min_heart_amount, max_heart_amount, daily_limit, is_enabled) VALUES
('quiz_perfect', '퀴즈 만점 참여자 보상', 20, 10, 30, 0, 1);

INSERT INTO heart_reward_settings (action_code, action_name, heart_amount, daily_limit, is_enabled) VALUES
('quiz_weekly_rank_1', '주간 퀴즈 참여 순위 1위', 1000, 0, 1),
('quiz_weekly_rank_2', '주간 퀴즈 참여 순위 2위', 500, 0, 1),
('quiz_weekly_rank_3', '주간 퀴즈 참여 순위 3위', 100, 0, 1);

CREATE TABLE fan_posts (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '팬포스트 ID',
    idol_idx BIGINT UNSIGNED NULL COMMENT '대상 개인 또는 그룹; Other 팬포스트는 NULL',
    member_idx BIGINT UNSIGNED NOT NULL COMMENT '공개 작성자',
    actor_member_idx BIGINT UNSIGNED NOT NULL COMMENT '최초 실제 로그인 작업자; 수정 시 덮어쓰지 않음',
    media_type VARCHAR(16) NOT NULL DEFAULT 'text' COMMENT 'text, image, or youtube',
    language_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'ISO 639-1 original post language; NULL for legacy posts without a recorded language',
    title VARCHAR(100) NOT NULL COMMENT 'Fan Post title',
    image_path VARCHAR(1024) NULL COMMENT '이미지 1장 저장 경로',
    youtube_video_id CHAR(11) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'Normalized YouTube video ID',
    content TEXT NULL COMMENT '공개 본문',
    status VARCHAR(16) NOT NULL DEFAULT 'published' COMMENT 'published, hidden, queued, or cancelled',
    queued_at DATETIME(6) NULL COMMENT '관리자 분산 발행 대기열 진입 시각',
    published_at DATETIME(6) NULL COMMENT '실제 공개 시각; 기존 글은 created_at으로 대체',
    view_count BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '조회수; 좋아요/랭킹과 독립',
    deleted_at DATETIME(6) NULL COMMENT '삭제 시각',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '작성 또는 신청 승인 시각',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '본문/상태 수정 시각; 조회수 증가 시 유지',
    PRIMARY KEY (idx),
    KEY idx_posts_idol_idx_status_deleted_at_created_at_idx (idol_idx, status, deleted_at, created_at, idx),
    KEY idx_posts_status_deleted_at_queued_at_idx (status, deleted_at, queued_at, idx),
    KEY idx_posts_member_idx_deleted_at_created_at_idx (member_idx, deleted_at, created_at, idx),
    KEY idx_posts_actor_member_idx_created_at_idx (actor_member_idx, created_at, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='아이돌별 팬포스트; 이미지 또는 본문 중 하나 이상 필수';


CREATE TABLE fan_post_tags (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Fan Post tag ID',
    fan_post_idx BIGINT UNSIGNED NOT NULL COMMENT 'Tagged Fan Post',
    tag VARCHAR(30) NOT NULL COMMENT 'Display tag without the # prefix',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Tag creation time',
    PRIMARY KEY (idx),
    UNIQUE KEY uq_fan_post_tags_fan_post_idx_tag (fan_post_idx, tag),
    KEY idx_fan_post_tags_tag_fan_post_idx (tag, fan_post_idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Fan Post display and search tags';

CREATE TABLE fan_post_translations (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Fan Post translation ID',
    fan_post_idx BIGINT UNSIGNED NOT NULL COMMENT 'Translated Fan Post',
    language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Target language code',
    translated_title VARCHAR(100) NOT NULL COMMENT 'Translated Fan Post title',
    translated_content TEXT NULL COMMENT 'Translated Fan Post content',
    source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256 of the original title and content used for this translation',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Translation cache creation time',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Translation cache update time',
    PRIMARY KEY (idx),
    UNIQUE KEY uq_fan_post_translations_fan_post_idx_language_code (fan_post_idx, language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cached Fan Post translations by target language';

CREATE TABLE fan_post_likes (
    actor_member_idx BIGINT UNSIGNED NULL COMMENT 'Actual authenticated actor; differs when an Admin acts as a dummy member',
    fan_post_idx BIGINT UNSIGNED NOT NULL COMMENT '좋아요 대상 게시물',
    member_idx BIGINT UNSIGNED NOT NULL COMMENT '직접 로그인한 회원',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '좋아요 시각',
    PRIMARY KEY (fan_post_idx, member_idx),
    KEY idx_likes_member_idx_fan_post_idx (member_idx, fan_post_idx),
    KEY idx_likes_actor_member_idx_created_at_idx (actor_member_idx, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='회원별 팬포스트 좋아요; 하트/투표와 무관, 취소 시 관계 삭제';

CREATE TABLE fan_post_comments (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '댓글 ID',
    fan_post_idx BIGINT UNSIGNED NOT NULL COMMENT '부모 게시물',
    member_idx BIGINT UNSIGNED NOT NULL COMMENT '공개 댓글 작성자',
    actor_member_idx BIGINT UNSIGNED NOT NULL COMMENT '최초 실제 로그인 작업자',
    request_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '실제 작업자 기준 댓글 등록 요청 중복 방지 키; 동일 요청 재시도는 기존 댓글 반환',
    content TEXT NOT NULL COMMENT '댓글 본문; 대댓글은 지원하지 않음',
    language_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'ISO 639-1 원문 댓글 언어; 과거 또는 확인되지 않은 댓글은 NULL',
    status VARCHAR(16) NOT NULL DEFAULT 'published' COMMENT 'published 또는 hidden',
    deleted_at DATETIME(6) NULL COMMENT '삭제 시각',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '댓글 작성 시각',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '최근 수정 시각',
    PRIMARY KEY (idx),
    UNIQUE KEY uq_comments_actor_member_idx_request_key (actor_member_idx, request_key),
    KEY idx_comments_fan_post_idx_status_deleted_at_created_at_idx (fan_post_idx, status, deleted_at, created_at, idx),
    KEY idx_comments_member_idx_deleted_at_created_at_idx (member_idx, deleted_at, created_at, idx),
    KEY idx_comments_actor_member_idx_created_at_idx (actor_member_idx, created_at, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='팬포스트 댓글; 보상 금액과 지급 여부는 하트 이력에서 확인';

CREATE TABLE fan_post_comment_translations (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '댓글 번역 ID',
    comment_idx BIGINT UNSIGNED NOT NULL COMMENT '번역 대상 댓글',
    language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '번역 대상 언어 코드',
    translated_content TEXT NOT NULL COMMENT '번역 댓글 본문',
    source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '원문 댓글 본문 SHA-256',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '번역 캐시 생성 시각',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '번역 캐시 갱신 시각',
    PRIMARY KEY (idx),
    UNIQUE KEY uq_fan_post_comment_translations_comment_idx_language_code (comment_idx, language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='대상 언어별 팬포스트 댓글 번역 캐시';

CREATE TABLE fan_post_reports (
    actor_member_idx BIGINT UNSIGNED NULL COMMENT 'Actual authenticated actor; differs when an Admin acts as a dummy member',
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Fan Post report ID',
    fan_post_idx BIGINT UNSIGNED NOT NULL COMMENT 'Reported Fan Post',
    member_idx BIGINT UNSIGNED NOT NULL COMMENT 'Reporting member',
    reason VARCHAR(32) NOT NULL COMMENT 'spam, harassment_hate, inappropriate_content, copyright, misinformation, or other',
    details TEXT NULL COMMENT 'Optional report details; required by the application for other',
    status VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending, reviewing, reviewed, or rejected',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_fan_post_reports_fan_post_idx_member_idx (fan_post_idx, member_idx),
    KEY idx_fan_post_reports_status_created_at_idx (status, created_at, idx),
    KEY idx_fan_post_reports_member_idx_created_at_idx (member_idx, created_at, idx),
    KEY idx_fan_post_reports_actor_member_idx_created_at_idx (actor_member_idx, created_at, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Member reports for published Fan Posts';

CREATE TABLE fan_post_comment_reports (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Fan Post comment report ID',
    comment_idx BIGINT UNSIGNED NOT NULL COMMENT 'Reported comment',
    member_idx BIGINT UNSIGNED NOT NULL COMMENT 'Reporting activity member',
    actor_member_idx BIGINT UNSIGNED NOT NULL COMMENT 'Authenticated actor who submitted the report',
    reason VARCHAR(32) NOT NULL COMMENT 'spam, harassment_hate, inappropriate_content, or other',
    details TEXT NULL COMMENT 'Optional context; required by the application for other',
    status VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending, reviewing, reviewed, or rejected',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_fan_post_comment_reports_comment_idx_member_idx (comment_idx, member_idx),
    KEY idx_fan_post_comment_reports_status_created_at_idx (status, created_at, idx),
    KEY idx_fan_post_comment_reports_member_idx_created_at_idx (member_idx, created_at, idx),
    KEY idx_fan_post_comment_reports_actor_member_idx_created_at_idx (actor_member_idx, created_at, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Member reports for published Fan Post comments';

CREATE TABLE idol_profile_image_reports (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Idol/group profile image report ID',
    idol_idx BIGINT UNSIGNED NOT NULL COMMENT 'Reported individual idol or group',
    member_idx BIGINT UNSIGNED NOT NULL COMMENT 'Activity member associated with the report',
    actor_member_idx BIGINT UNSIGNED NOT NULL COMMENT 'Authenticated member who submitted the report',
    reported_image_path VARCHAR(1024) NOT NULL COMMENT 'Original public image path reported by the member',
    image_path_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256 of the reported image path for duplicate prevention',
    evidence_path VARCHAR(1024) NULL COMMENT 'Private retained evidence path after image removal',
    reason VARCHAR(32) NOT NULL COMMENT 'copyright, portrait_rights, or other',
    details TEXT NULL COMMENT 'Optional report details; required by the application for other',
    status VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending, reviewing, reviewed, or rejected',
    moderated_by_member_idx BIGINT UNSIGNED NULL COMMENT 'Administrator who last moderated this report',
    moderated_at DATETIME(6) NULL COMMENT 'Last moderation action time',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_idol_profile_image_reports_idol_actor_hash (idol_idx, actor_member_idx, image_path_hash),
    KEY idx_idol_profile_image_reports_status_created_idx (status, created_at, idx),
    KEY idx_idol_profile_image_reports_member_created_idx (member_idx, created_at, idx),
    KEY idx_idol_profile_image_reports_actor_created_idx (actor_member_idx, created_at, idx),
    KEY idx_idol_profile_image_reports_moderator_created_idx (moderated_by_member_idx, moderated_at, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Member rights reports for individual idol and group profile images';

CREATE TABLE idol_registration_requests (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '아이돌 등록 신청 ID',
    member_idx BIGINT UNSIGNED NOT NULL COMMENT '공개 신청자; 관리자 등록 시 선택한 더미',
    actor_member_idx BIGINT UNSIGNED NOT NULL COMMENT '실제 신청 작업자; 관리자 등록 시 관리자',
    type VARCHAR(16) NOT NULL COMMENT 'individual 또는 group',
    gender VARCHAR(8) NOT NULL COMMENT 'male 또는 female',
    name VARCHAR(200) NOT NULL COMMENT '신청 아이돌 이름',
    english_name VARCHAR(200) NOT NULL COMMENT 'English display name supplied with the request',
    group_idx BIGINT UNSIGNED NULL COMMENT '개인 신청의 기존 소속 그룹; 그룹 신청은 NULL',
    birth_date DATE NULL COMMENT 'Date of birth for an individual',
    height_cm DECIMAL(5,2) NULL COMMENT 'Height in centimeters',
    weight_kg DECIMAL(5,2) NULL COMMENT 'Weight in kilograms',
    blood_type VARCHAR(8) NULL COMMENT 'A, B, AB, or O',
    agency VARCHAR(200) NULL COMMENT 'Submitted agency name',
    position VARCHAR(200) NULL COMMENT 'Group position or role',
    genre VARCHAR(200) NULL COMMENT 'Requested group genre; NULL for individuals',
    leader VARCHAR(200) NULL COMMENT 'Requested leader name as free text; NULL for individuals',
    sns VARCHAR(1000) NULL COMMENT 'Legacy single SNS URL or handle',
    sns_links TEXT NULL COMMENT 'JSON array of up to ten validated public SNS URLs',
    image_path VARCHAR(1024) NOT NULL COMMENT '필수 신청 이미지; 승인 팬포스트에 그대로 사용',
    content TEXT NOT NULL COMMENT '공개 소개; 입력하지 않으면 빈 문자열로 저장하고 승인 팬포스트에 사용',
    reference_url VARCHAR(2048) NULL COMMENT '아이돌 확인용 참고 주소',
    note TEXT NULL COMMENT '검토자에게 전달할 비공개 설명',
    request_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '실제 작업자 기준 동일 신청 재전송 식별키',
    status VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected',
    approved_idol_idx BIGINT UNSIGNED NULL COMMENT '승인으로 생성하거나 연결한 아이돌',
    approved_fan_post_idx BIGINT UNSIGNED NULL COMMENT '승인 과정에서 생성한 팬포스트',
    reviewed_by_member_idx BIGINT UNSIGNED NULL COMMENT '승인/반려 관리자; 원래 작업자와 별개',
    reviewed_at DATETIME(6) NULL COMMENT '승인 또는 반려 시각',
    review_note TEXT NULL COMMENT '검토 기록; 반려 시 필수',
    rejection_reason VARCHAR(1000) NULL COMMENT '신청자에게 공개할 반려 사유',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '신청 시각',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '최근 처리 시각',
    PRIMARY KEY (idx),
    UNIQUE KEY uq_requests_actor_member_idx_request_key (actor_member_idx, request_key),
    UNIQUE KEY uq_requests_approved_fan_post_idx (approved_fan_post_idx),
    KEY idx_requests_status_created_at_idx (status, created_at, idx),
    KEY idx_requests_member_idx_created_at_idx (member_idx, created_at, idx),
    KEY idx_requests_actor_member_idx_created_at_idx (actor_member_idx, created_at, idx),
    KEY idx_requests_group_idx (group_idx),
    KEY idx_requests_approved_idol_idx (approved_idol_idx),
    KEY idx_requests_reviewed_by_member_idx (reviewed_by_member_idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='모든 아이돌 등록의 단일 진입점; 승인 결과와 실제 작업자 보존';

-- 모든 테이블 생성 후 FK 추가. 기본 NO ACTION은 InnoDB에서 즉시 RESTRICT와 동일하다.
CREATE TABLE idol_registration_request_debuts (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_idx BIGINT UNSIGNED NOT NULL,
    country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    debut_date DATE NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_idol_request_debuts_request_country (request_idx, country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Submitted country-specific debut dates for idol registration requests';
CREATE TABLE idol_registration_request_localizations (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_idx BIGINT UNSIGNED NOT NULL,
    language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    text_role VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'submitted or reviewed; validated by application code',
    localized_name VARCHAR(200) NULL,
    description TEXT NULL,
    agency VARCHAR(200) NULL,
    position VARCHAR(200) NULL,
    genre VARCHAR(200) NULL,
    leader VARCHAR(200) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_idol_request_localizations_request_language_role (request_idx, language_code, text_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Submitted and reviewed localized text for idol registration requests';

CREATE TABLE admin_activity_logs (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_member_idx BIGINT UNSIGNED NOT NULL COMMENT 'Administrator who performed the action',
    action VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_idx BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(1000) NULL COMMENT 'Internal moderation reason',
    summary VARCHAR(1000) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    KEY idx_admin_activity_actor_created (actor_member_idx, created_at, idx),
    KEY idx_admin_activity_target_created (target_type, target_idx, created_at, idx),
    KEY idx_admin_activity_action_created (action, created_at, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Audit trail for administrator moderation actions';

CREATE TABLE admin_dummy_activity_requests (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_member_idx BIGINT UNSIGNED NOT NULL,
    member_idx BIGINT UNSIGNED NOT NULL,
    action_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    result_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    result_idx BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    completed_at DATETIME(6) NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_admin_dummy_requests_actor_action_key (actor_member_idx, action_code, request_key),
    KEY idx_admin_dummy_requests_member_action_created (member_idx, action_code, created_at, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Idempotency and dummy attribution for random-mode administrator activity';

CREATE TABLE visitor_daily_stats (
    stat_date DATE NOT NULL,
    page_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
    unique_visitors BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Long-term daily page-view and unique-visitor aggregate';

CREATE TABLE visitor_daily_uniques (
    stat_date DATE NOT NULL,
    visitor_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Random first-party visitor identifier',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (stat_date, visitor_id),
    KEY idx_visitor_daily_uniques_visitor_date (visitor_id, stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Long-term visitor/day identities for daily and period UV';

CREATE TABLE member_access_logs (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Site access log ID',
    member_idx BIGINT UNSIGNED NULL COMMENT 'Authenticated member; NULL for anonymous visitors',
    visitor_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'Random first-party visitor identifier; NULL for detected bots',
    is_bot TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 when excluded from analytics by User-Agent detection',
    ip_address VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'NULL for privacy-conscious visitor tracking; legacy values are retained until log expiry',
    request_path VARCHAR(1024) NOT NULL COMMENT 'Request path only; query strings are excluded',
    user_agent VARCHAR(1024) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    KEY idx_member_access_created_at_idx (created_at, idx),
    KEY idx_member_access_member_idx_created_at_idx (member_idx, created_at, idx),
    KEY idx_member_access_ip_created_at_idx (ip_address, created_at, idx),
    KEY idx_member_access_bot_created_at_idx (is_bot, created_at, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Recent public page access records with limited retention';

CREATE TABLE restricted_nicknames (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    word VARCHAR(50) NOT NULL,
    match_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'exact',
    is_enabled TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_restricted_nicknames_word_match (word, match_type),
    KEY idx_restricted_nicknames_enabled_idx (is_enabled, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE blocked_ips (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    created_by_member_idx BIGINT UNSIGNED NOT NULL,
    expires_at DATETIME(6) NULL,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    KEY idx_blocked_ips_address_active_expires (ip_address, is_active, expires_at),
    KEY idx_blocked_ips_creator_created (created_by_member_idx, created_at, idx),
    CONSTRAINT fk_blocked_ips_created_by_member_idx FOREIGN KEY (created_by_member_idx) REFERENCES members (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE site_settings (
    setting_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    setting_value MEDIUMTEXT NULL,
    updated_by_member_idx BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (setting_key),
    KEY idx_site_settings_updated_by (updated_by_member_idx, updated_at),
    CONSTRAINT fk_site_settings_updated_by_member_idx FOREIGN KEY (updated_by_member_idx) REFERENCES members (idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE members
    ADD COLUMN referred_by_member_idx BIGINT UNSIGNED NULL COMMENT 'Inviting member for this account, if registered through a referral',
    ADD KEY idx_members_referred_by_member_idx (referred_by_member_idx);

ALTER TABLE members
    ADD CONSTRAINT fk_members_created_by_member_idx FOREIGN KEY (created_by_member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_members_referred_by_member_idx FOREIGN KEY (referred_by_member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_members_favorite_idol_1_idx FOREIGN KEY (favorite_idol_1_idx) REFERENCES idols (idx),
    ADD CONSTRAINT fk_members_favorite_idol_2_idx FOREIGN KEY (favorite_idol_2_idx) REFERENCES idols (idx),
    ADD CONSTRAINT fk_members_favorite_idol_3_idx FOREIGN KEY (favorite_idol_3_idx) REFERENCES idols (idx);

ALTER TABLE member_social_accounts
    ADD CONSTRAINT fk_social_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx);

ALTER TABLE member_login_histories
    ADD CONSTRAINT fk_login_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx);

ALTER TABLE member_access_logs
    ADD CONSTRAINT fk_member_access_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx);

CREATE TABLE daily_check_ins (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_idx BIGINT UNSIGNED NOT NULL,
    actor_member_idx BIGINT UNSIGNED NOT NULL,
    language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'Original message language; NULL when unknown',
    content VARCHAR(500) NOT NULL,
    awarded_hearts BIGINT UNSIGNED NOT NULL,
    check_in_date DATE NOT NULL COMMENT 'UTC calendar date used for the daily uniqueness rule',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_daily_check_ins_member_idx_check_in_date (member_idx, check_in_date),
    KEY idx_daily_check_ins_check_in_date_idx (check_in_date, idx),
    KEY idx_daily_check_ins_actor_member_idx_idx (actor_member_idx, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE daily_check_in_translations (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    daily_check_in_idx BIGINT UNSIGNED NOT NULL,
    source_language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    translated_content TEXT NOT NULL,
    source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256 of the exact original check-in message',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idx),
    UNIQUE KEY uq_daily_check_in_translations_check_in_source_target (daily_check_in_idx, source_language_code, target_language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cached Daily Check-in translations';

ALTER TABLE daily_check_ins
    ADD CONSTRAINT fk_daily_check_ins_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_daily_check_ins_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx);

ALTER TABLE daily_check_in_translations
    ADD CONSTRAINT fk_daily_check_in_translations_check_in_idx FOREIGN KEY (daily_check_in_idx) REFERENCES daily_check_ins (idx);

ALTER TABLE password_reset_tokens
    ADD CONSTRAINT fk_password_reset_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx);

ALTER TABLE idol_group_members
    ADD CONSTRAINT fk_group_members_individual_idx FOREIGN KEY (individual_idx) REFERENCES idols (idx),
    ADD CONSTRAINT fk_group_members_group_idx FOREIGN KEY (group_idx) REFERENCES idols (idx);

ALTER TABLE idol_votes
    ADD CONSTRAINT fk_votes_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_votes_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_votes_idol_idx FOREIGN KEY (idol_idx) REFERENCES idols (idx);

ALTER TABLE heart_histories
    ADD CONSTRAINT fk_hearts_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_hearts_vote_idx FOREIGN KEY (vote_idx) REFERENCES idol_votes (idx),
    ADD CONSTRAINT fk_hearts_comment_idx FOREIGN KEY (comment_idx) REFERENCES fan_post_comments (idx),
    ADD CONSTRAINT fk_hearts_reversal_of_history_idx FOREIGN KEY (reversal_of_history_idx) REFERENCES heart_histories (idx),
    ADD CONSTRAINT fk_hearts_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx);

ALTER TABLE fan_posts
    ADD CONSTRAINT fk_posts_idol_idx FOREIGN KEY (idol_idx) REFERENCES idols (idx),
    ADD CONSTRAINT fk_posts_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_posts_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx);

ALTER TABLE fan_post_tags
    ADD CONSTRAINT fk_fan_post_tags_fan_post_idx FOREIGN KEY (fan_post_idx) REFERENCES fan_posts (idx);

ALTER TABLE fan_post_translations
    ADD CONSTRAINT fk_fan_post_translations_fan_post_idx FOREIGN KEY (fan_post_idx) REFERENCES fan_posts (idx);

ALTER TABLE fan_post_likes
    ADD CONSTRAINT fk_likes_fan_post_idx FOREIGN KEY (fan_post_idx) REFERENCES fan_posts (idx),
    ADD CONSTRAINT fk_likes_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_likes_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx);

ALTER TABLE fan_post_comments
    ADD CONSTRAINT fk_comments_fan_post_idx FOREIGN KEY (fan_post_idx) REFERENCES fan_posts (idx),
    ADD CONSTRAINT fk_comments_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_comments_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx);

ALTER TABLE fan_post_comment_translations
    ADD CONSTRAINT fk_fan_post_comment_translations_comment_idx FOREIGN KEY (comment_idx) REFERENCES fan_post_comments (idx);

ALTER TABLE fan_post_reports
    ADD CONSTRAINT fk_fan_post_reports_fan_post_idx FOREIGN KEY (fan_post_idx) REFERENCES fan_posts (idx),
    ADD CONSTRAINT fk_fan_post_reports_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_fan_post_reports_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx);

ALTER TABLE fan_post_comment_reports
    ADD CONSTRAINT fk_fan_post_comment_reports_comment_idx FOREIGN KEY (comment_idx) REFERENCES fan_post_comments (idx),
    ADD CONSTRAINT fk_fan_post_comment_reports_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_fan_post_comment_reports_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx);

ALTER TABLE idol_profile_image_reports
    ADD CONSTRAINT fk_idol_profile_image_reports_idol_idx FOREIGN KEY (idol_idx) REFERENCES idols (idx),
    ADD CONSTRAINT fk_idol_profile_image_reports_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_idol_profile_image_reports_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_idol_profile_image_reports_moderated_by_member_idx FOREIGN KEY (moderated_by_member_idx) REFERENCES members (idx);

ALTER TABLE idol_registration_requests
    ADD CONSTRAINT fk_requests_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_requests_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_requests_group_idx FOREIGN KEY (group_idx) REFERENCES idols (idx),
    ADD CONSTRAINT fk_requests_approved_idol_idx FOREIGN KEY (approved_idol_idx) REFERENCES idols (idx),
    ADD CONSTRAINT fk_requests_approved_fan_post_idx FOREIGN KEY (approved_fan_post_idx) REFERENCES fan_posts (idx),
    ADD CONSTRAINT fk_requests_reviewed_by_member_idx FOREIGN KEY (reviewed_by_member_idx) REFERENCES members (idx);

ALTER TABLE idol_localizations
    ADD CONSTRAINT fk_idol_localizations_idol_idx FOREIGN KEY (idol_idx) REFERENCES idols (idx);

ALTER TABLE idol_debuts
    ADD CONSTRAINT fk_idol_debuts_idol_idx FOREIGN KEY (idol_idx) REFERENCES idols (idx);

ALTER TABLE idol_name_aliases
    ADD CONSTRAINT fk_idol_name_aliases_idol_idx FOREIGN KEY (idol_idx) REFERENCES idols (idx);

ALTER TABLE idol_registration_request_localizations
    ADD CONSTRAINT fk_idol_request_localizations_request_idx FOREIGN KEY (request_idx) REFERENCES idol_registration_requests (idx);

ALTER TABLE idol_registration_request_debuts
    ADD CONSTRAINT fk_idol_request_debuts_request_idx FOREIGN KEY (request_idx) REFERENCES idol_registration_requests (idx);

ALTER TABLE admin_activity_logs
    ADD CONSTRAINT fk_admin_activity_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx);

ALTER TABLE admin_dummy_activity_requests
    ADD CONSTRAINT fk_admin_dummy_requests_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx),
    ADD CONSTRAINT fk_admin_dummy_requests_member_idx FOREIGN KEY (member_idx) REFERENCES members (idx);

-- CHECK 제약은 사용하지 않으며 업무 값 검증은 Laravel 애플리케이션에서 수행한다.
-- 컬럼 COMMENT의 허용값/필수 조건은 업무 규칙이다. DB는 PK/FK/UNIQUE/NOT NULL/타입/DEFAULT/INDEX를 유지한다.
-- member_level/is_dummy/status/type/gender/provider/login_type/result/reason 허용값을 검사한다.
-- 비탈퇴 회원 이메일/닉네임, 더미 생성자/비밀번호, 활성 회원 국가 및 탈퇴 상태 일치를 검사한다.
-- 로그인 성공은 회원 ID 필수/실패 사유 NULL, 실패는 내부 사유 코드 필수이다.
-- 이름/식별키/댓글은 빈 값 금지. 일반 팬포스트는 이미지 또는 본문 필수, 신청은 둘 다 필수이다.
-- 그룹 신청의 group_idx는 NULL이며, 소속의 개인 ID와 그룹 ID는 달라야 한다.
-- 투표량>0, 하트 amount!=0 및 reason별 부호/관계/작업자/조정·회수 사유를 검사한다.
-- vote만 vote_idx 필수 및 작업자=회원, 댓글 보상/회수만 comment_idx 필수, 회수만 원본 ID 필수이다.
-- 관리자 조정은 작업자 필수. 기타 사유의 해당 관계 ID는 NULL이다.
-- 신청 pending은 결과/검토 정보 NULL, approved는 결과/검토 정보 필수,
-- rejected는 결과 NULL 및 검토자/시각/반려 사유 필수이다.
-- 애플리케이션에서 보장할 세부 규칙:
-- 1. 이메일/닉네임 정규화, 유효 ISO 국가코드 목록, 이미지 파일 및 공백뿐인 본문 검사.
--    국가 선택은 일반/Google 가입 완료 및 활성 관리자/더미에도 필수이며 IP로 확정하지 않는다.
-- 2. 현재 레벨 0/1/10만 허용. 일반/Google 가입은 1/0, 관리자 생성 더미는 1/1로 지정.
--    권한은 요청 입력을 신뢰하지 않는다. Google 연결은 더미 금지 및 검증된 sub 사용.
-- 3. 직접 로그인은 level>0, active, deleted_at IS NULL을 검사한다. 성공 이력과
--    last_login_at 변경은 한 트랜잭션; 대리작성은 대상 더미의 로그인 이력이 아니다.
-- 4. 비밀번호 재설정은 비밀번호가 있는 활성 회원만 허용. 회원/토큰 행을 잠그고
--    만료/미사용을 검사한 뒤 해시 변경, password_changed_at, used_at을 함께 기록한다.
--    재발급/이메일 변경/탈퇴 시 토큰 무효화, 변경 후 기존 세션 무효화 및 메일 발송을 처리한다.
-- 5. 대리작성은 활성 관리자 -> 활성 일반 레벨 더미만 허용. actor는 실제 세션에서 결정.
--    공개 화면은 member 기준이며 최초 member/actor는 수정 시 덮어쓰지 않는다.
-- 6. 소속의 실제 type/gender 일치, 검토자의 관리자 권한과 대상 상태를 검사한다.
--    관리자도 더미 명의 신청을 생성하고 공통 승인 로직을 즉시 실행한다.
--    신청 잠금 -> pending 확인 -> 아이돌 생성/연결 -> 소속 -> 팬포스트 -> 승인 변경은 원자적이다.
--    팬포스트는 신청의 image_path/content/member_idx/actor_member_idx를 그대로 사용한다.
--    관리자 즉시 등록은 신청 생성까지 같은 트랜잭션. 파일 저장 자체는 DB 롤백 대상이 아니다.
-- 7. 모든 하트 처리에서 회원 행을 먼저 잠근다. 잔액=이력 합계, 투표량=-차감량,
--    투표/댓글/원본 지급의 회원 일치 및 투표당 차감 이력 존재는 같은 트랜잭션에서 보장한다.
--    amount가 signed BIGINT이므로 1회 지급/차감과 투표량은 그 표현 범위 내로 검증한다.
-- 8. operation_key는 검증된 지급 사건에서 고정 생성한다. 광고 사건은 수령 회원까지 검증한다.
--    동일 키 재시도는 기존 결과 반환, 금액/대상/사유가 다르면 거절한다.
-- 9. 댓글 보상은 level=1, is_dummy=0, active, deleted_at IS NULL 및 member=actor만 허용.
--    댓글 생성/잔액 증가/이력은 한 트랜잭션, 키는 comment_reward:{comment_idx}로 고정한다.
--    금액/일일 횟수/기준 시간대는 운영 설정. 회원 잠금 아래 지급 횟수를 세며 회수해도 횟수 유지.
--    같은 댓글 ID 재지급 방지와 별도로 반복 댓글/스팸/요청 재전송을 검사한다.
-- 10. 회수는 원본 comment_reward와 동일 회원/댓글, 반대 전액인지 검사한다.
--     키는 comment_reward_reversal:{원본_하트이력_idx}. 잔액 부족 시 회수를 실패 처리한다.
--     원본 이력과 완료 투표는 수정/삭제하지 않으며 자기 참조 회수도 금지한다.
-- 11. 랭킹은 idol_votes.heart_amount 합계. 조회수/좋아요/보유 잔액은 점수에서 제외한다.
--     조회수는 세션별 단시간 중복과 동시 요청을 제한하고 원자적으로 증가시킨다.
-- 12. 탈퇴는 level=0/inactive/deleted_at과 개인정보 정리, Google 연결 삭제 및 토큰 무효화로 처리.
--     잔액/이력은 보존하며 로그/IP와 만료 토큰의 보관 기간은 별도 운영 정책으로 관리한다.
