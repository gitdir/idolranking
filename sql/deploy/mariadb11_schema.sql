-- IdolRanking complete fresh-install schema for MariaDB 11.8 / InnoDB
-- Generated from the current local MySQL 8.4 schema; contains no application table data.
-- Use utf8mb4_unicode_ci for MySQL 8.4 / MariaDB 11.8 compatibility.
-- Review sql/README.md before deployment. DDL statements commit independently in MariaDB.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE schema_patch_history (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    patch_name VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    applied_by VARCHAR(100) NULL,
    notes VARCHAR(1000) NULL,
    PRIMARY KEY (idx),
    UNIQUE KEY uq_schema_patch_history_patch_name (patch_name),
    KEY idx_schema_patch_history_applied_at (applied_at, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sequential SQL patches successfully applied to this database';

CREATE TABLE `heart_reward_settings` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `action_code` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `action_name` varchar(100) NOT NULL,
  `heart_amount` bigint unsigned NOT NULL DEFAULT '0',
  `min_heart_amount` bigint unsigned DEFAULT NULL,
  `max_heart_amount` bigint unsigned DEFAULT NULL,
  `daily_limit` int unsigned NOT NULL DEFAULT '0' COMMENT '0 means unlimited; fixed one-time rules use operation_key',
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_heart_reward_settings_action_code` (`action_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `idols` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '개인 또는 그룹 ID',
  `type` varchar(16) NOT NULL COMMENT 'individual 개인, group 그룹',
  `gender` varchar(8) NOT NULL COMMENT 'male 남자/남자그룹, female 여자/여자그룹',
  `name` varchar(200) NOT NULL COMMENT '공식 표시 이름; 동명이인 허용',
  `english_name` varchar(200) DEFAULT NULL,
  `agency` varchar(200) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `blood_type` varchar(8) DEFAULT NULL,
  `position` varchar(200) DEFAULT NULL,
  `genre` varchar(200) DEFAULT NULL COMMENT 'Group genre; NULL for individuals',
  `leader` varchar(200) DEFAULT NULL COMMENT 'Leader name as free text; NULL for individuals',
  `sns` varchar(1000) DEFAULT NULL,
  `sns_links` text COMMENT 'JSON array of up to ten validated public SNS URLs',
  `image_path` varchar(1024) DEFAULT NULL,
  `content` text,
  `status` varchar(16) NOT NULL DEFAULT 'active' COMMENT 'active 또는 inactive',
  `view_count` bigint unsigned NOT NULL DEFAULT '0' COMMENT '누적 조회수; 랭킹 점수와 무관',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '등록 승인으로 생성된 시각',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '정보 수정 시각; 조회수 증가 시 유지',
  PRIMARY KEY (`idx`),
  KEY `idx_idols_type_gender_status_idx` (`type`,`gender`,`status`,`idx`),
  KEY `idx_idols_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='개인 아이돌 및 그룹; 생성 경로는 등록 신청 승인으로 통일';

CREATE TABLE `idol_localizations` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idol_idx` bigint unsigned NOT NULL,
  `language_code` varchar(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `localized_name` varchar(200) DEFAULT NULL,
  `description` text,
  `agency` varchar(200) DEFAULT NULL,
  `position` varchar(200) DEFAULT NULL,
  `genre` varchar(200) DEFAULT NULL,
  `leader` varchar(200) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_idol_localizations_idol_language` (`idol_idx`,`language_code`),
  CONSTRAINT `fk_idol_localizations_idol_idx` FOREIGN KEY (`idol_idx`) REFERENCES `idols` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reviewed localized names and descriptions for idols and groups';
CREATE TABLE `idol_debuts` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idol_idx` bigint unsigned NOT NULL,
  `country_code` char(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `debut_date` date NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_idol_debuts_idol_country` (`idol_idx`,`country_code`),
  CONSTRAINT `fk_idol_debuts_idol_idx` FOREIGN KEY (`idol_idx`) REFERENCES `idols` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Country-specific idol and group debut dates';

CREATE TABLE `idol_name_aliases` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idol_idx` bigint unsigned NOT NULL,
  `language_code` varchar(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `alias_name` varchar(200) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  KEY `idx_idol_name_aliases_idol_language` (`idol_idx`,`language_code`),
  KEY `idx_idol_name_aliases_language_name` (`language_code`,`alias_name`),
  CONSTRAINT `fk_idol_name_aliases_idol_idx` FOREIGN KEY (`idol_idx`) REFERENCES `idols` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Additional language-tagged idol and group names';

CREATE TABLE `members` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '회원 ID',
  `member_code` varchar(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Public immutable member identifier',
  `email` varchar(254) DEFAULT NULL COMMENT '이메일 로그인 주소; 탈퇴 시 NULL',
  `password_hash` varchar(255) DEFAULT NULL COMMENT '비밀번호 해시; Google 전용 또는 탈퇴 회원은 NULL',
  `nickname` varchar(50) DEFAULT NULL COMMENT '공개 닉네임; 탈퇴 시 NULL',
  `member_level` smallint unsigned NOT NULL DEFAULT '1' COMMENT '0 탈퇴, 1 일반, 10 관리자; 중간 권한은 애플리케이션 정책으로 추가',
  `is_dummy` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0 일반 계정, 1 관리자 생성 더미계정',
  `favorite_idol_1_idx` bigint unsigned DEFAULT NULL COMMENT 'Member favorite idol or group, priority 1',
  `favorite_idol_2_idx` bigint unsigned DEFAULT NULL COMMENT 'Member favorite idol or group, priority 2',
  `favorite_idol_3_idx` bigint unsigned DEFAULT NULL COMMENT 'Member favorite idol or group, priority 3',
  `status` varchar(16) NOT NULL DEFAULT 'active' COMMENT 'active, suspended, inactive',
  `country_code` char(2) DEFAULT NULL COMMENT '사용자 선택 ISO 3166-1 alpha-2 대문자 코드; 탈퇴 시 NULL 가능',
  `profile_image_path` varchar(1024) DEFAULT NULL COMMENT '회원 프로필 이미지 공개 저장 경로',
  `heart_balance` bigint unsigned NOT NULL DEFAULT '0' COMMENT '현재 하트 잔액; 하트 이력 합계와 일치해야 함',
  `email_verified_at` datetime(6) DEFAULT NULL COMMENT '현재 이메일 확인 완료 시각',
  `last_login_at` datetime(6) DEFAULT NULL COMMENT '최근 정상 로그인 시각',
  `password_changed_at` datetime(6) DEFAULT NULL COMMENT '최초 비밀번호 설정 또는 최근 변경 시각',
  `created_by_member_idx` bigint unsigned DEFAULT NULL COMMENT '더미계정을 생성한 관리자; 일반 가입은 NULL',
  `referred_by_member_idx` bigint unsigned DEFAULT NULL COMMENT 'Inviting member for this account, if registered through a referral',
  `deleted_at` datetime(6) DEFAULT NULL COMMENT '탈퇴 시각',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '가입 시각',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '최근 수정 시각',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_members_member_code` (`member_code`),
  UNIQUE KEY `uq_members_email` (`email`),
  UNIQUE KEY `uq_members_nickname` (`nickname`),
  KEY `idx_members_is_dummy_member_level_status_idx` (`is_dummy`,`member_level`,`status`,`idx`),
  KEY `idx_members_favorite_idol_1_idx` (`favorite_idol_1_idx`),
  KEY `idx_members_favorite_idol_2_idx` (`favorite_idol_2_idx`),
  KEY `idx_members_favorite_idol_3_idx` (`favorite_idol_3_idx`),
  KEY `idx_members_created_by_member_idx` (`created_by_member_idx`),
  KEY `idx_members_country_code_idx` (`country_code`,`idx`),
  KEY `idx_members_referred_by_member_idx` (`referred_by_member_idx`),
  CONSTRAINT `fk_members_created_by_member_idx` FOREIGN KEY (`created_by_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_members_referred_by_member_idx` FOREIGN KEY (`referred_by_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_members_favorite_idol_1_idx` FOREIGN KEY (`favorite_idol_1_idx`) REFERENCES `idols` (`idx`),
  CONSTRAINT `fk_members_favorite_idol_2_idx` FOREIGN KEY (`favorite_idol_2_idx`) REFERENCES `idols` (`idx`),
  CONSTRAINT `fk_members_favorite_idol_3_idx` FOREIGN KEY (`favorite_idol_3_idx`) REFERENCES `idols` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='회원, 권한, 국가 및 현재 하트 잔액';

CREATE TABLE `partner_banners` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Admin-only banner title and accessible image text',
  `image_path` varchar(1024) NOT NULL COMMENT 'Owned upload under storage/partner-banners/',
  `target_url` varchar(2048) NOT NULL,
  `heart_reward` int unsigned NOT NULL DEFAULT '10',
  `reward_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `display_order` int NOT NULL DEFAULT '0',
  `starts_at` datetime(6) DEFAULT NULL,
  `ends_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  KEY `idx_partner_banners_active_order_idx` (`is_active`,`display_order`,`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Public partner banners and reward configuration';

CREATE TABLE `restricted_nicknames` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `word` varchar(50) NOT NULL,
  `match_type` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'exact' COMMENT 'exact or contains',
  `is_enabled` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_restricted_nicknames_word_match` (`word`,`match_type`),
  KEY `idx_restricted_nicknames_enabled_idx` (`is_enabled`,`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `visitor_daily_stats` (
  `stat_date` date NOT NULL,
  `page_views` bigint unsigned NOT NULL DEFAULT '0',
  `unique_visitors` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Privacy-friendly daily unique session visitor aggregate';

CREATE TABLE `visitor_daily_uniques` (
  `stat_date` date NOT NULL,
  `visitor_id` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Random first-party visitor identifier',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`stat_date`,`visitor_id`),
  KEY `idx_visitor_daily_uniques_visitor_date` (`visitor_id`,`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Long-term visitor/day identities for daily and period UV';

CREATE TABLE `admin_activity_logs` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_member_idx` bigint unsigned NOT NULL COMMENT 'Administrator who performed the action',
  `action` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `target_type` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `target_idx` bigint unsigned NOT NULL,
  `reason` varchar(1000) DEFAULT NULL COMMENT 'Internal moderation reason',
  `summary` varchar(1000) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  KEY `idx_admin_activity_actor_created` (`actor_member_idx`,`created_at`,`idx`),
  KEY `idx_admin_activity_target_created` (`target_type`,`target_idx`,`created_at`,`idx`),
  KEY `idx_admin_activity_action_created` (`action`,`created_at`,`idx`),
  CONSTRAINT `fk_admin_activity_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for administrator moderation actions';

CREATE TABLE `admin_dummy_activity_requests` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_member_idx` bigint unsigned NOT NULL,
  `member_idx` bigint unsigned NOT NULL,
  `action_code` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `request_key` varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payload_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `result_type` varchar(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `result_idx` bigint unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `completed_at` datetime(6) DEFAULT NULL,
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_admin_dummy_requests_actor_action_key` (`actor_member_idx`,`action_code`,`request_key`),
  KEY `idx_admin_dummy_requests_member_action_created` (`member_idx`,`action_code`,`created_at`,`idx`),
  CONSTRAINT `fk_admin_dummy_requests_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_admin_dummy_requests_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Idempotency and dummy attribution for random-mode administrator activity';

CREATE TABLE `blocked_ips` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL COMMENT 'NULL for privacy-conscious visitor tracking; legacy values are retained until log expiry',
  `reason` varchar(1000) NOT NULL,
  `created_by_member_idx` bigint unsigned NOT NULL,
  `expires_at` datetime(6) DEFAULT NULL,
  `is_active` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  KEY `idx_blocked_ips_address_active_expires` (`ip_address`,`is_active`,`expires_at`),
  KEY `idx_blocked_ips_creator_created` (`created_by_member_idx`,`created_at`,`idx`),
  CONSTRAINT `fk_blocked_ips_created_by_member_idx` FOREIGN KEY (`created_by_member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fan_posts` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '팬포스트 ID',
  `idol_idx` bigint unsigned DEFAULT NULL COMMENT '대상 개인 또는 그룹; Other 팬포스트는 NULL',
  `member_idx` bigint unsigned NOT NULL COMMENT '공개 작성자',
  `actor_member_idx` bigint unsigned NOT NULL COMMENT '최초 실제 로그인 작업자; 수정 시 덮어쓰지 않음',
  `media_type` varchar(16) NOT NULL DEFAULT 'text' COMMENT 'text, image, or youtube',
  `language_code` char(2) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL COMMENT 'ISO 639-1 original post language; NULL for legacy posts without a recorded language',
  `title` varchar(100) NOT NULL COMMENT 'Fan Post title',
  `image_path` varchar(1024) DEFAULT NULL COMMENT '이미지 1장 저장 경로',
  `youtube_video_id` char(11) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `content` text COMMENT '공개 본문',
  `status` varchar(16) NOT NULL DEFAULT 'published' COMMENT 'published, hidden, queued, or cancelled',
  `queued_at` datetime(6) DEFAULT NULL COMMENT '관리자 분산 발행 대기열 진입 시각',
  `published_at` datetime(6) DEFAULT NULL COMMENT '실제 공개 시각; 기존 글은 created_at으로 대체',
  `view_count` bigint unsigned NOT NULL DEFAULT '0' COMMENT '조회수; 좋아요/랭킹과 독립',
  `deleted_at` datetime(6) DEFAULT NULL COMMENT '삭제 시각',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '작성 또는 신청 승인 시각',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '본문/상태 수정 시각; 조회수 증가 시 유지',
  PRIMARY KEY (`idx`),
  KEY `idx_posts_idol_idx_status_deleted_at_created_at_idx` (`idol_idx`,`status`,`deleted_at`,`created_at`,`idx`),
  KEY `idx_posts_status_deleted_at_queued_at_idx` (`status`,`deleted_at`,`queued_at`,`idx`),
  KEY `idx_posts_member_idx_deleted_at_created_at_idx` (`member_idx`,`deleted_at`,`created_at`,`idx`),
  KEY `idx_posts_actor_member_idx_created_at_idx` (`actor_member_idx`,`created_at`,`idx`),
  CONSTRAINT `fk_posts_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_posts_idol_idx` FOREIGN KEY (`idol_idx`) REFERENCES `idols` (`idx`),
  CONSTRAINT `fk_posts_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='아이돌별 팬포스트; 이미지 또는 본문 중 하나 이상 필수';

CREATE TABLE `idol_group_members` (
  `individual_idx` bigint unsigned NOT NULL COMMENT 'type=individual인 idols ID',
  `group_idx` bigint unsigned NOT NULL COMMENT 'type=group인 idols ID',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '소속 연결 시각',
  PRIMARY KEY (`individual_idx`,`group_idx`),
  KEY `idx_group_members_group_idx_individual_idx` (`group_idx`,`individual_idx`),
  CONSTRAINT `fk_group_members_group_idx` FOREIGN KEY (`group_idx`) REFERENCES `idols` (`idx`),
  CONSTRAINT `fk_group_members_individual_idx` FOREIGN KEY (`individual_idx`) REFERENCES `idols` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='개인과 그룹의 다대다 소속; 유형/성별 일치는 애플리케이션 검사';

CREATE TABLE `idol_votes` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '완료된 투표 ID',
  `member_idx` bigint unsigned NOT NULL COMMENT '직접 로그인해 하트를 사용한 회원',
  `actor_member_idx` bigint unsigned DEFAULT NULL,
  `idol_idx` bigint unsigned NOT NULL COMMENT '투표 대상 개인 또는 그룹',
  `heart_amount` bigint unsigned NOT NULL COMMENT '실제 사용한 하트 수; 랭킹은 대상별 합계',
  `country_code_at_vote` char(2) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `request_key` varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '회원별 동일 투표 요청 재전송 식별키',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '투표 완료 시각',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_votes_member_idx_request_key` (`member_idx`,`request_key`),
  KEY `idx_votes_idol_idx_created_at_idx` (`idol_idx`,`created_at`,`idx`),
  KEY `idx_votes_member_idx_created_at_idx` (`member_idx`,`created_at`,`idx`),
  KEY `idx_votes_actor_member_idx_created_at_idx` (`actor_member_idx`,`created_at`,`idx`),
  CONSTRAINT `fk_votes_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_votes_idol_idx` FOREIGN KEY (`idol_idx`) REFERENCES `idols` (`idx`),
  CONSTRAINT `fk_votes_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='수정/삭제하지 않는 완료 투표; 잔액 차감 및 이력과 원자적으로 생성';

CREATE TABLE `member_access_logs` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Site access log ID',
  `member_idx` bigint unsigned DEFAULT NULL COMMENT 'Authenticated member; NULL for anonymous visitors',
  `visitor_id` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL COMMENT 'Random first-party visitor identifier; NULL for detected bots',
  `is_bot` tinyint unsigned NOT NULL DEFAULT '0',
  `ip_address` varchar(45) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL COMMENT 'NULL for privacy-conscious visitor tracking; legacy values are retained until log expiry',
  `request_path` varchar(1024) NOT NULL COMMENT 'Request path only; query strings are excluded',
  `user_agent` varchar(1024) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  KEY `idx_member_access_created_at_idx` (`created_at`,`idx`),
  KEY `idx_member_access_member_idx_created_at_idx` (`member_idx`,`created_at`,`idx`),
  KEY `idx_member_access_ip_created_at_idx` (`ip_address`,`created_at`,`idx`),
  KEY `idx_member_access_bot_created_at_idx` (`is_bot`,`created_at`,`idx`),
  CONSTRAINT `fk_member_access_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recent public page access records with limited retention';

CREATE TABLE `member_login_histories` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '로그인 이력 ID',
  `member_idx` bigint unsigned DEFAULT NULL COMMENT '식별한 회원 ID; 미등록 계정 등 식별 불가 실패는 NULL',
  `login_type` varchar(16) NOT NULL COMMENT 'password 또는 google',
  `result` varchar(16) NOT NULL COMMENT 'success 또는 failed',
  `ip_address` varchar(45) DEFAULT NULL COMMENT '신뢰된 프록시 정책으로 확인한 IPv4/IPv6; 확인 불가 시 NULL',
  `user_agent` varchar(1024) DEFAULT NULL COMMENT '길이를 제한한 User-Agent; 비밀번호/토큰/세션 ID 저장 금지',
  `failure_reason` varchar(64) DEFAULT NULL COMMENT '실패 내부 코드; 성공 시 NULL, 상세 오류 원문 금지',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '로그인 시도 결과 기록 시각',
  PRIMARY KEY (`idx`),
  KEY `idx_login_member_idx_created_at_idx` (`member_idx`,`created_at`,`idx`),
  KEY `idx_login_ip_address_created_at_idx` (`ip_address`,`created_at`,`idx`),
  KEY `idx_login_created_at_idx` (`created_at`,`idx`),
  CONSTRAINT `fk_login_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='일반회원/관리자/더미의 직접 로그인 성공 및 실패 이력';

CREATE TABLE `member_social_accounts` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '소셜 연결 ID',
  `member_idx` bigint unsigned NOT NULL COMMENT '연결 회원 ID; 더미 연결 금지는 애플리케이션에서 검사',
  `provider` varchar(16) NOT NULL COMMENT '현재 google만 허용',
  `provider_user_id` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Google sub; 이메일이 아닌 대소문자 구분 외부 식별값',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '연결 시각',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '최근 수정 시각',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_social_provider_user` (`provider`,`provider_user_id`),
  UNIQUE KEY `uq_social_member_idx_provider` (`member_idx`,`provider`),
  CONSTRAINT `fk_social_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='회원별 Google 계정 연결; 인증 토큰 원문 저장 금지';

CREATE TABLE `partner_banner_clicks` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `banner_idx` bigint unsigned NOT NULL,
  `member_idx` bigint unsigned DEFAULT NULL,
  `clicked_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  KEY `idx_partner_banner_clicks_banner_idx_clicked_at` (`banner_idx`,`clicked_at`),
  KEY `idx_partner_banner_clicks_member_idx_clicked_at` (`member_idx`,`clicked_at`),
  CONSTRAINT `fk_partner_banner_clicks_banner_idx` FOREIGN KEY (`banner_idx`) REFERENCES `partner_banners` (`idx`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Activated partner banner clicks from guests and eligible normal members only; same subject/banner repeats within two seconds are ignored';

CREATE TABLE `password_reset_tokens` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '비밀번호 재설정 요청 ID',
  `member_idx` bigint unsigned NOT NULL COMMENT '재설정 대상 회원 ID',
  `token_hash` binary(32) NOT NULL COMMENT '무작위 재설정 토큰의 SHA-256 바이너리 해시; 원문 저장 금지',
  `expires_at` datetime(6) NOT NULL COMMENT '만료 시각; 재발급/회원정보 변경 시 앞당겨 무효화 가능',
  `used_at` datetime(6) DEFAULT NULL COMMENT '실제 비밀번호 변경 완료 시각; 미사용은 NULL',
  `requested_ip` varchar(45) DEFAULT NULL COMMENT '요청 IPv4/IPv6; 확인 불가 시 NULL',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '발급 시각',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_password_reset_hash` (`token_hash`),
  KEY `idx_password_reset_member_idx_used_at_expires_at` (`member_idx`,`used_at`,`expires_at`),
  KEY `idx_password_reset_expires_at_idx` (`expires_at`,`idx`),
  CONSTRAINT `fk_password_reset_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='만료시간이 있는 일회용 비밀번호 재설정 토큰 해시';

CREATE TABLE `site_settings` (
  `setting_key` varchar(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `setting_value` mediumtext,
  `updated_by_member_idx` bigint unsigned NOT NULL,
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`setting_key`),
  KEY `idx_site_settings_updated_by` (`updated_by_member_idx`,`updated_at`),
  CONSTRAINT `fk_site_settings_updated_by_member_idx` FOREIGN KEY (`updated_by_member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fan_post_comments` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '댓글 ID',
  `fan_post_idx` bigint unsigned NOT NULL COMMENT '부모 게시물',
  `member_idx` bigint unsigned NOT NULL COMMENT '공개 댓글 작성자',
  `actor_member_idx` bigint unsigned NOT NULL COMMENT '최초 실제 로그인 작업자',
  `request_key` varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '실제 작업자 기준 댓글 등록 요청 중복 방지 키; 동일 요청 재시도는 기존 댓글 반환',
  `content` text NOT NULL COMMENT '댓글 본문; 대댓글은 지원하지 않음',
  `language_code` char(2) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL COMMENT 'ISO 639-1 원문 댓글 언어; 과거 또는 확인되지 않은 댓글은 NULL',
  `status` varchar(16) NOT NULL DEFAULT 'published' COMMENT 'published 또는 hidden',
  `deleted_at` datetime(6) DEFAULT NULL COMMENT '삭제 시각',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '댓글 작성 시각',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '최근 수정 시각',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_comments_actor_member_idx_request_key` (`actor_member_idx`,`request_key`),
  KEY `idx_comments_fan_post_idx_status_deleted_at_created_at_idx` (`fan_post_idx`,`status`,`deleted_at`,`created_at`,`idx`),
  KEY `idx_comments_member_idx_deleted_at_created_at_idx` (`member_idx`,`deleted_at`,`created_at`,`idx`),
  KEY `idx_comments_actor_member_idx_created_at_idx` (`actor_member_idx`,`created_at`,`idx`),
  CONSTRAINT `fk_comments_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_comments_fan_post_idx` FOREIGN KEY (`fan_post_idx`) REFERENCES `fan_posts` (`idx`),
  CONSTRAINT `fk_comments_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='팬포스트 댓글; 보상 금액과 지급 여부는 하트 이력에서 확인';

CREATE TABLE `fan_post_comment_translations` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '댓글 번역 ID',
  `comment_idx` bigint unsigned NOT NULL COMMENT '번역 대상 댓글',
  `language_code` varchar(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '번역 대상 언어 코드',
  `translated_content` text NOT NULL COMMENT '번역 댓글 본문',
  `source_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '원문 댓글 본문 SHA-256',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '번역 캐시 생성 시각',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '번역 캐시 갱신 시각',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_fan_post_comment_translations_comment_idx_language_code` (`comment_idx`,`language_code`),
  CONSTRAINT `fk_fan_post_comment_translations_comment_idx` FOREIGN KEY (`comment_idx`) REFERENCES `fan_post_comments` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='대상 언어별 팬포스트 댓글 번역 캐시';

CREATE TABLE `fan_post_likes` (
  `fan_post_idx` bigint unsigned NOT NULL COMMENT '좋아요 대상 게시물',
  `member_idx` bigint unsigned NOT NULL COMMENT '직접 로그인한 회원',
  `actor_member_idx` bigint unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '좋아요 시각',
  PRIMARY KEY (`fan_post_idx`,`member_idx`),
  KEY `idx_likes_member_idx_fan_post_idx` (`member_idx`,`fan_post_idx`),
  KEY `idx_likes_actor_member_idx_created_at_idx` (`actor_member_idx`,`created_at`),
  CONSTRAINT `fk_likes_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_likes_fan_post_idx` FOREIGN KEY (`fan_post_idx`) REFERENCES `fan_posts` (`idx`),
  CONSTRAINT `fk_likes_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='회원별 팬포스트 좋아요; 하트/투표와 무관, 취소 시 관계 삭제';

CREATE TABLE `fan_post_reports` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Fan Post report ID',
  `fan_post_idx` bigint unsigned NOT NULL COMMENT 'Reported Fan Post',
  `member_idx` bigint unsigned NOT NULL COMMENT 'Reporting member',
  `actor_member_idx` bigint unsigned DEFAULT NULL,
  `reason` varchar(32) NOT NULL COMMENT 'spam, harassment_hate, inappropriate_content, copyright, misinformation, or other',
  `details` text COMMENT 'Optional report details; required by the application for other',
  `status` varchar(16) NOT NULL DEFAULT 'pending' COMMENT 'pending, reviewed, or rejected',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_fan_post_reports_fan_post_idx_member_idx` (`fan_post_idx`,`member_idx`),
  KEY `idx_fan_post_reports_status_created_at_idx` (`status`,`created_at`,`idx`),
  KEY `idx_fan_post_reports_member_idx_created_at_idx` (`member_idx`,`created_at`,`idx`),
  KEY `idx_fan_post_reports_actor_member_idx_created_at_idx` (`actor_member_idx`,`created_at`,`idx`),
  CONSTRAINT `fk_fan_post_reports_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_fan_post_reports_fan_post_idx` FOREIGN KEY (`fan_post_idx`) REFERENCES `fan_posts` (`idx`),
  CONSTRAINT `fk_fan_post_reports_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Member reports for published Fan Posts';

CREATE TABLE `fan_post_comment_reports` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Fan Post comment report ID',
  `comment_idx` bigint unsigned NOT NULL COMMENT 'Reported comment',
  `member_idx` bigint unsigned NOT NULL COMMENT 'Reporting activity member',
  `actor_member_idx` bigint unsigned NOT NULL COMMENT 'Authenticated actor who submitted the report',
  `reason` varchar(32) NOT NULL COMMENT 'spam, harassment_hate, inappropriate_content, or other',
  `details` text COMMENT 'Optional context; required by the application for other',
  `status` varchar(16) NOT NULL DEFAULT 'pending' COMMENT 'pending, reviewing, reviewed, or rejected',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_fan_post_comment_reports_comment_idx_member_idx` (`comment_idx`,`member_idx`),
  KEY `idx_fan_post_comment_reports_status_created_at_idx` (`status`,`created_at`,`idx`),
  KEY `idx_fan_post_comment_reports_member_idx_created_at_idx` (`member_idx`,`created_at`,`idx`),
  KEY `idx_fan_post_comment_reports_actor_member_idx_created_at_idx` (`actor_member_idx`,`created_at`,`idx`),
  CONSTRAINT `fk_fan_post_comment_reports_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_fan_post_comment_reports_comment_idx` FOREIGN KEY (`comment_idx`) REFERENCES `fan_post_comments` (`idx`),
  CONSTRAINT `fk_fan_post_comment_reports_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Member reports for published Fan Post comments';

CREATE TABLE `idol_profile_image_reports` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Idol/group profile image report ID',
  `idol_idx` bigint unsigned NOT NULL COMMENT 'Reported individual idol or group',
  `member_idx` bigint unsigned NOT NULL COMMENT 'Activity member associated with the report',
  `actor_member_idx` bigint unsigned NOT NULL COMMENT 'Authenticated member who submitted the report',
  `reported_image_path` varchar(1024) NOT NULL COMMENT 'Original public image path reported by the member',
  `image_path_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256 of the reported image path for duplicate prevention',
  `evidence_path` varchar(1024) DEFAULT NULL COMMENT 'Private retained evidence path after image removal',
  `reason` varchar(32) NOT NULL COMMENT 'copyright, portrait_rights, or other',
  `details` text COMMENT 'Optional report details; required by the application for other',
  `status` varchar(16) NOT NULL DEFAULT 'pending' COMMENT 'pending, reviewing, reviewed, or rejected',
  `moderated_by_member_idx` bigint unsigned DEFAULT NULL COMMENT 'Administrator who last moderated this report',
  `moderated_at` datetime(6) DEFAULT NULL COMMENT 'Last moderation action time',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_idol_profile_image_reports_idol_actor_hash` (`idol_idx`,`actor_member_idx`,`image_path_hash`),
  KEY `idx_idol_profile_image_reports_status_created_idx` (`status`,`created_at`,`idx`),
  KEY `idx_idol_profile_image_reports_member_created_idx` (`member_idx`,`created_at`,`idx`),
  KEY `idx_idol_profile_image_reports_actor_created_idx` (`actor_member_idx`,`created_at`,`idx`),
  KEY `idx_idol_profile_image_reports_moderator_created_idx` (`moderated_by_member_idx`,`moderated_at`,`idx`),
  CONSTRAINT `fk_idol_profile_image_reports_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_idol_profile_image_reports_idol_idx` FOREIGN KEY (`idol_idx`) REFERENCES `idols` (`idx`),
  CONSTRAINT `fk_idol_profile_image_reports_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_idol_profile_image_reports_moderated_by_member_idx` FOREIGN KEY (`moderated_by_member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Member rights reports for individual idol and group profile images';

CREATE TABLE `fan_post_tags` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Fan Post tag ID',
  `fan_post_idx` bigint unsigned NOT NULL COMMENT 'Tagged Fan Post',
  `tag` varchar(30) NOT NULL COMMENT 'Display tag without the # prefix',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Tag creation time',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_fan_post_tags_fan_post_idx_tag` (`fan_post_idx`,`tag`),
  KEY `idx_fan_post_tags_tag_fan_post_idx` (`tag`,`fan_post_idx`),
  CONSTRAINT `fk_fan_post_tags_fan_post_idx` FOREIGN KEY (`fan_post_idx`) REFERENCES `fan_posts` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fan Post display and search tags';

CREATE TABLE `fan_post_translations` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Fan Post translation ID',
  `fan_post_idx` bigint unsigned NOT NULL COMMENT 'Translated Fan Post',
  `language_code` varchar(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Target language code',
  `translated_title` varchar(100) NOT NULL COMMENT 'Translated Fan Post title',
  `translated_content` text DEFAULT NULL COMMENT 'Translated Fan Post content',
  `source_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256 of the original title and content used for this translation',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Translation cache creation time',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Translation cache update time',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_fan_post_translations_fan_post_idx_language_code` (`fan_post_idx`,`language_code`),
  CONSTRAINT `fk_fan_post_translations_fan_post_idx` FOREIGN KEY (`fan_post_idx`) REFERENCES `fan_posts` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cached Fan Post translations by target language';

CREATE TABLE `idol_registration_requests` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '아이돌 등록 신청 ID',
  `member_idx` bigint unsigned NOT NULL COMMENT '공개 신청자; 관리자 등록 시 선택한 더미',
  `actor_member_idx` bigint unsigned NOT NULL COMMENT '실제 신청 작업자; 관리자 등록 시 관리자',
  `type` varchar(16) NOT NULL COMMENT 'individual 또는 group',
  `gender` varchar(8) NOT NULL COMMENT 'male 또는 female',
  `name` varchar(200) NOT NULL COMMENT '신청 아이돌 이름',
  `english_name` varchar(200) NOT NULL,
  `group_idx` bigint unsigned DEFAULT NULL COMMENT '개인 신청의 기존 소속 그룹; 그룹 신청은 NULL',
  `birth_date` date DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `blood_type` varchar(8) DEFAULT NULL,
  `agency` varchar(200) DEFAULT NULL,
  `position` varchar(200) DEFAULT NULL,
  `genre` varchar(200) DEFAULT NULL COMMENT 'Requested group genre; NULL for individuals',
  `leader` varchar(200) DEFAULT NULL COMMENT 'Requested leader name as free text; NULL for individuals',
  `sns` varchar(1000) DEFAULT NULL,
  `sns_links` text COMMENT 'JSON array of up to ten validated public SNS URLs',
  `image_path` varchar(1024) NOT NULL COMMENT '필수 신청 이미지; 승인 팬포스트에 그대로 사용',
  `content` text NOT NULL COMMENT '공개 소개; 입력하지 않으면 빈 문자열로 저장하고 승인 팬포스트에 사용',
  `reference_url` varchar(2048) DEFAULT NULL COMMENT '아이돌 확인용 참고 주소',
  `note` text COMMENT '검토자에게 전달할 비공개 설명',
  `request_key` varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '실제 작업자 기준 동일 신청 재전송 식별키',
  `status` varchar(16) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected',
  `approved_idol_idx` bigint unsigned DEFAULT NULL COMMENT '승인으로 생성하거나 연결한 아이돌',
  `approved_fan_post_idx` bigint unsigned DEFAULT NULL COMMENT '승인 과정에서 생성한 팬포스트',
  `reviewed_by_member_idx` bigint unsigned DEFAULT NULL COMMENT '승인/반려 관리자; 원래 작업자와 별개',
  `reviewed_at` datetime(6) DEFAULT NULL COMMENT '승인 또는 반려 시각',
  `review_note` text COMMENT '검토 기록; 반려 시 필수',
  `rejection_reason` varchar(1000) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '신청 시각',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '최근 처리 시각',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_requests_actor_member_idx_request_key` (`actor_member_idx`,`request_key`),
  UNIQUE KEY `uq_requests_approved_fan_post_idx` (`approved_fan_post_idx`),
  KEY `idx_requests_status_created_at_idx` (`status`,`created_at`,`idx`),
  KEY `idx_requests_member_idx_created_at_idx` (`member_idx`,`created_at`,`idx`),
  KEY `idx_requests_actor_member_idx_created_at_idx` (`actor_member_idx`,`created_at`,`idx`),
  KEY `idx_requests_group_idx` (`group_idx`),
  KEY `idx_requests_approved_idol_idx` (`approved_idol_idx`),
  KEY `idx_requests_reviewed_by_member_idx` (`reviewed_by_member_idx`),
  CONSTRAINT `fk_requests_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_requests_approved_fan_post_idx` FOREIGN KEY (`approved_fan_post_idx`) REFERENCES `fan_posts` (`idx`),
  CONSTRAINT `fk_requests_approved_idol_idx` FOREIGN KEY (`approved_idol_idx`) REFERENCES `idols` (`idx`),
  CONSTRAINT `fk_requests_group_idx` FOREIGN KEY (`group_idx`) REFERENCES `idols` (`idx`),
  CONSTRAINT `fk_requests_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_requests_reviewed_by_member_idx` FOREIGN KEY (`reviewed_by_member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='모든 아이돌 등록의 단일 진입점; 승인 결과와 실제 작업자 보존';

CREATE TABLE `idol_registration_request_debuts` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_idx` bigint unsigned NOT NULL,
  `country_code` char(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `debut_date` date NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_idol_request_debuts_request_country` (`request_idx`,`country_code`),
  CONSTRAINT `fk_idol_request_debuts_request_idx` FOREIGN KEY (`request_idx`) REFERENCES `idol_registration_requests` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Submitted country-specific debut dates for idol registration requests';
CREATE TABLE `idol_registration_request_localizations` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_idx` bigint unsigned NOT NULL,
  `language_code` varchar(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `text_role` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'submitted or reviewed; validated by application code',
  `localized_name` varchar(200) DEFAULT NULL,
  `description` text,
  `agency` varchar(200) DEFAULT NULL,
  `position` varchar(200) DEFAULT NULL,
  `genre` varchar(200) DEFAULT NULL,
  `leader` varchar(200) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_idol_request_localizations_request_language_role` (`request_idx`,`language_code`,`text_role`),
  CONSTRAINT `fk_idol_request_localizations_request_idx` FOREIGN KEY (`request_idx`) REFERENCES `idol_registration_requests` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Submitted and reviewed localized text for idol registration requests';

CREATE TABLE `heart_histories` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '하트 증감 이력 ID',
  `member_idx` bigint unsigned NOT NULL COMMENT '하트 잔액 대상 회원',
  `amount` bigint NOT NULL COMMENT '양수 지급, 음수 차감; 0 금지',
  `balance_after` bigint unsigned NOT NULL COMMENT '해당 처리 직후 잔액',
  `reason` varchar(32) NOT NULL COMMENT 'signup_reward/ad_reward/event_reward/comment_reward/vote/admin_adjustment/comment_reward_reversal',
  `vote_idx` bigint unsigned DEFAULT NULL COMMENT 'vote일 때만 연결되는 투표 ID',
  `comment_idx` bigint unsigned DEFAULT NULL COMMENT '댓글 보상 및 보상 회수 대상 댓글 ID',
  `reversal_of_history_idx` bigint unsigned DEFAULT NULL COMMENT '전액 회수 대상 원본 댓글 보상 이력 ID; 원본당 1회 회수',
  `operation_key` varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '회원별 지급/차감 사건의 고정 중복 방지 키',
  `actor_member_idx` bigint unsigned DEFAULT NULL COMMENT '실제 작업자; 시스템 지급/회수는 NULL',
  `memo` varchar(1000) DEFAULT NULL COMMENT '관리자 조정 또는 보상 회수 사유',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '증감 처리 시각',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_hearts_member_idx_operation_key` (`member_idx`,`operation_key`),
  UNIQUE KEY `uq_hearts_vote_idx` (`vote_idx`),
  UNIQUE KEY `uq_hearts_reversal_of_history_idx` (`reversal_of_history_idx`),
  KEY `idx_hearts_member_idx_idx` (`member_idx`,`idx`),
  KEY `idx_hearts_member_idx_created_at_idx` (`member_idx`,`created_at`,`idx`),
  KEY `idx_hearts_member_idx_reason_created_at_idx` (`member_idx`,`reason`,`created_at`,`idx`),
  KEY `idx_hearts_actor_member_idx_idx` (`actor_member_idx`,`idx`),
  KEY `idx_hearts_comment_idx` (`comment_idx`),
  CONSTRAINT `fk_hearts_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_hearts_comment_idx` FOREIGN KEY (`comment_idx`) REFERENCES `fan_post_comments` (`idx`),
  CONSTRAINT `fk_hearts_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_hearts_reversal_of_history_idx` FOREIGN KEY (`reversal_of_history_idx`) REFERENCES `heart_histories` (`idx`),
  CONSTRAINT `fk_hearts_vote_idx` FOREIGN KEY (`vote_idx`) REFERENCES `idol_votes` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='추가 기록 방식의 하트 원장; 기존 이력 변경/삭제는 애플리케이션에서 금지';

CREATE TABLE `daily_check_ins` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_idx` bigint unsigned NOT NULL,
  `actor_member_idx` bigint unsigned NOT NULL,
  `language_code` varchar(10) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL COMMENT 'Original message language; NULL when unknown',
  `content` varchar(500) NOT NULL,
  `awarded_hearts` bigint unsigned NOT NULL,
  `check_in_date` date NOT NULL COMMENT 'UTC calendar date used for the daily uniqueness rule',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_daily_check_ins_member_idx_check_in_date` (`member_idx`,`check_in_date`),
  KEY `idx_daily_check_ins_check_in_date_idx` (`check_in_date`,`idx`),
  KEY `idx_daily_check_ins_actor_member_idx_idx` (`actor_member_idx`,`idx`),
  CONSTRAINT `fk_daily_check_ins_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_daily_check_ins_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `daily_check_in_translations` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `daily_check_in_idx` bigint unsigned NOT NULL,
  `source_language_code` varchar(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `target_language_code` varchar(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `translated_content` text NOT NULL,
  `source_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256 of the exact original check-in message',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_daily_check_in_translations_check_in_source_target` (`daily_check_in_idx`,`source_language_code`,`target_language_code`),
  CONSTRAINT `fk_daily_check_in_translations_check_in_idx` FOREIGN KEY (`daily_check_in_idx`) REFERENCES `daily_check_ins` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cached Daily Check-in translations';

CREATE TABLE `quizzes` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_idx` bigint unsigned NOT NULL COMMENT 'Quiz creator',
  `actor_member_idx` bigint unsigned NOT NULL COMMENT 'Authenticated creator',
  `title` varchar(255) NOT NULL COMMENT 'Quiz title',
  `idol_idx` bigint unsigned DEFAULT NULL COMMENT 'Selected active idol or group',
  `cover_image_path` varchar(1024) DEFAULT NULL COMMENT 'Optional managed quiz cover image',
  `question_count` tinyint unsigned NOT NULL,
  `quiz_version` int unsigned NOT NULL DEFAULT 1 COMMENT 'Current immutable question set version',
  `deleted_at` datetime(6) DEFAULT NULL COMMENT 'Soft deletion timestamp; quiz history remains intact',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  KEY `idx_quizzes_idol_idx_created_at_idx` (`idol_idx`,`created_at`,`idx`),
  KEY `idx_quizzes_member_idx_created_at_idx` (`member_idx`,`created_at`,`idx`),
  KEY `idx_quizzes_actor_member_idx_idx` (`actor_member_idx`,`idx`),
  KEY `idx_quizzes_deleted_at_created_at` (`deleted_at`,`created_at`,`idx`),
  CONSTRAINT `fk_quizzes_idol_idx` FOREIGN KEY (`idol_idx`) REFERENCES `idols` (`idx`),
  CONSTRAINT `fk_quizzes_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quizzes_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Member-created quizzes';

CREATE TABLE `quiz_likes` (
  `quiz_idx` bigint unsigned NOT NULL,
  `member_idx` bigint unsigned NOT NULL,
  `actor_member_idx` bigint unsigned DEFAULT NULL COMMENT 'Authenticated actor; differs when an Admin acts as a dummy member',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`quiz_idx`,`member_idx`),
  KEY `idx_quiz_likes_member_idx_quiz_idx` (`member_idx`,`quiz_idx`),
  CONSTRAINT `fk_quiz_likes_quiz_idx` FOREIGN KEY (`quiz_idx`) REFERENCES `quizzes` (`idx`),
  CONSTRAINT `fk_quiz_likes_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quiz_likes_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Member Likes on quizzes';

CREATE TABLE `quiz_creation_histories` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_idx` bigint unsigned NOT NULL COMMENT 'Quiz creator',
  `quiz_idx` bigint unsigned NOT NULL COMMENT 'Created quiz ID; intentionally has no FK so quota history survives quiz deletion',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'UTC instant; daily quota uses Asia/Seoul calendar boundaries',
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_quiz_creation_histories_quiz_idx` (`quiz_idx`),
  KEY `idx_quiz_creation_histories_member_created_at` (`member_idx`,`created_at`),
  CONSTRAINT `fk_quiz_creation_histories_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Persistent quiz creation events for daily quotas';

CREATE TABLE `quiz_questions` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quiz_idx` bigint unsigned NOT NULL,
  `quiz_version` int unsigned NOT NULL DEFAULT 1 COMMENT 'Question set version',
  `question_order` tinyint unsigned NOT NULL,
  `question_type` varchar(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'image, choice, or ox',
  `prompt` varchar(500) NOT NULL,
  `image_path` varchar(1024) DEFAULT NULL,
  `correct_choice_order` tinyint unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_quiz_questions_quiz_version_order` (`quiz_idx`,`quiz_version`,`question_order`),
  UNIQUE KEY `uq_quiz_questions_idx_quiz` (`idx`,`quiz_idx`),
  CONSTRAINT `fk_quiz_questions_quiz_idx` FOREIGN KEY (`quiz_idx`) REFERENCES `quizzes` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordered quiz questions and answer keys';

CREATE TABLE `quiz_question_choices` (
  `question_idx` bigint unsigned NOT NULL,
  `choice_order` tinyint unsigned NOT NULL,
  `choice_text` varchar(200) NOT NULL,
  PRIMARY KEY (`question_idx`,`choice_order`),
  CONSTRAINT `fk_quiz_question_choices_question_idx` FOREIGN KEY (`question_idx`) REFERENCES `quiz_questions` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordered question choices; OX stores O and X';

CREATE TABLE `quiz_attempts` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quiz_idx` bigint unsigned NOT NULL,
  `member_idx` bigint unsigned NOT NULL,
  `actor_member_idx` bigint unsigned NOT NULL,
  `attempt_date` date NOT NULL COMMENT 'Calendar date in app.timezone',
  `status` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'in_progress',
  `question_count` tinyint unsigned NOT NULL,
  `quiz_version` int unsigned NOT NULL DEFAULT 1 COMMENT 'Question set version captured at attempt start',
  `is_test_only` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Creator-owned test attempt, excluded from rewards and weekly rankings',
  `score` tinyint unsigned DEFAULT NULL,
  `is_perfect` tinyint(1) DEFAULT NULL,
  `awarded_hearts` bigint unsigned NOT NULL DEFAULT 0,
  `started_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `completed_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  KEY `idx_quiz_attempts_quiz_member_date` (`quiz_idx`,`member_idx`,`attempt_date`,`idx`),
  UNIQUE KEY `uq_quiz_attempts_idx_quiz` (`idx`,`quiz_idx`),
  KEY `idx_quiz_attempts_member_date_idx` (`member_idx`,`attempt_date`,`idx`),
  KEY `idx_quiz_attempts_actor_member_idx_idx` (`actor_member_idx`,`idx`),
  KEY `idx_quiz_attempts_status_completed_at` (`status`,`completed_at`),
  CONSTRAINT `fk_quiz_attempts_actor_member_idx` FOREIGN KEY (`actor_member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quiz_attempts_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quiz_attempts_quiz_idx` FOREIGN KEY (`quiz_idx`) REFERENCES `quizzes` (`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily member attempts and creator-owned test retries';

CREATE TABLE `quiz_attempt_answers` (
  `attempt_idx` bigint unsigned NOT NULL,
  `quiz_idx` bigint unsigned NOT NULL,
  `question_idx` bigint unsigned NOT NULL,
  `question_order` tinyint unsigned NOT NULL,
  `selected_choice_order` tinyint unsigned NOT NULL,
  `is_correct` tinyint(1) DEFAULT NULL COMMENT 'Set only after completion',
  `submitted_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`attempt_idx`,`question_order`),
  UNIQUE KEY `uq_quiz_attempt_answers_attempt_question` (`attempt_idx`,`question_idx`),
  KEY `idx_quiz_attempt_answers_attempt_quiz` (`attempt_idx`,`quiz_idx`),
  KEY `idx_quiz_attempt_answers_question_quiz` (`question_idx`,`quiz_idx`),
  KEY `idx_quiz_attempt_answers_question_choice` (`question_idx`,`selected_choice_order`),
  CONSTRAINT `fk_quiz_attempt_answers_attempt_quiz` FOREIGN KEY (`attempt_idx`,`quiz_idx`) REFERENCES `quiz_attempts` (`idx`,`quiz_idx`),
  CONSTRAINT `fk_quiz_attempt_answers_question_quiz` FOREIGN KEY (`question_idx`,`quiz_idx`) REFERENCES `quiz_questions` (`idx`,`quiz_idx`),
  CONSTRAINT `fk_quiz_attempt_answers_choice` FOREIGN KEY (`question_idx`,`selected_choice_order`) REFERENCES `quiz_question_choices` (`question_idx`,`choice_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable submitted quiz answers';

CREATE TABLE `quiz_weekly_settlements` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `week_start_date` date NOT NULL COMMENT 'Monday in Asia/Seoul',
  `week_end_date` date NOT NULL COMMENT 'Exclusive next Monday in Asia/Seoul',
  `settled_at` datetime(6) DEFAULT NULL COMMENT 'UTC settlement completion time; NULL only while the transaction is open',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_quiz_weekly_settlements_week_start` (`week_start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Idempotent weekly Quiz ranking settlement headers';

CREATE TABLE `quiz_weekly_settlement_rewards` (
  `idx` bigint unsigned NOT NULL AUTO_INCREMENT,
  `settlement_idx` bigint unsigned NOT NULL,
  `rank_position` tinyint unsigned NOT NULL,
  `member_idx` bigint unsigned NOT NULL,
  `quiz_idx` bigint unsigned NULL COMMENT 'Ranked quiz; NULL for legacy member-ranking settlements or deleted quizzes',
  `participation_count` bigint unsigned NOT NULL,
  `reached_at` datetime(6) NOT NULL COMMENT 'UTC time when the member reached their final weekly count',
  `reward_amount` bigint unsigned NOT NULL,
  `operation_key` varchar(191) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uq_quiz_weekly_settlement_rank` (`settlement_idx`,`rank_position`),
  UNIQUE KEY `uq_quiz_weekly_settlement_operation` (`operation_key`),
  KEY `idx_quiz_weekly_settlement_rewards_member` (`member_idx`,`settlement_idx`),
  KEY `idx_quiz_weekly_settlement_rewards_quiz` (`quiz_idx`,`settlement_idx`),
  CONSTRAINT `fk_quiz_weekly_settlement_rewards_settlement_idx` FOREIGN KEY (`settlement_idx`) REFERENCES `quiz_weekly_settlements` (`idx`),
  CONSTRAINT `fk_quiz_weekly_settlement_rewards_member_idx` FOREIGN KEY (`member_idx`) REFERENCES `members` (`idx`),
  CONSTRAINT `fk_quiz_weekly_settlement_rewards_quiz_idx` FOREIGN KEY (`quiz_idx`) REFERENCES `quizzes` (`idx`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditable occupied Quiz ranking rewards by week';
