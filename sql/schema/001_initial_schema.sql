-- IdolRanking 1차 초기 스키마 / MySQL 8.4
-- 대상 DB를 선택한 상태에서 빈 스키마에 한 번 적용하는 파일이다.
-- CREATE DATABASE, USE, 데이터 입력, 기존 테이블 삭제는 포함하지 않는다.
-- DDL은 암묵적으로 커밋되므로 이 파일 전체가 하나의 트랜잭션은 아니다.
-- 모든 DATETIME은 애플리케이션/연결 시간대를 UTC로 맞춰 사용한다.
-- updated_at은 애플리케이션에서 변경한다. 조회수 증가만으로 갱신하지 않는다.
-- FK는 ON DELETE/ON UPDATE 절을 생략하여 기본 NO ACTION(RESTRICT)을 사용한다.
-- FK 검사 비활성화, CASCADE, 트리거 및 저장 프로시저는 사용하지 않는다.

CREATE TABLE members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '회원 ID',
    email VARCHAR(254) NULL COMMENT '이메일 로그인 주소; 탈퇴 시 NULL',
    password_hash VARCHAR(255) NULL COMMENT '비밀번호 해시; Google 전용 또는 탈퇴 회원은 NULL',
    nickname VARCHAR(50) NULL COMMENT '공개 닉네임; 탈퇴 시 NULL',
    member_level SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0 탈퇴, 1 일반, 10 관리자; 중간 권한은 애플리케이션 정책으로 추가',
    is_dummy TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 일반 계정, 1 관리자 생성 더미계정',
    status VARCHAR(16) NOT NULL DEFAULT 'active' COMMENT 'active, suspended, inactive',
    country_code CHAR(2) NULL COMMENT '사용자 선택 ISO 3166-1 alpha-2 대문자 코드; 탈퇴 시 NULL 가능',
    heart_balance BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '현재 하트 잔액; 하트 이력 합계와 일치해야 함',
    email_verified_at DATETIME(6) NULL COMMENT '현재 이메일 확인 완료 시각',
    last_login_at DATETIME(6) NULL COMMENT '최근 정상 로그인 시각',
    password_changed_at DATETIME(6) NULL COMMENT '최초 비밀번호 설정 또는 최근 변경 시각',
    created_by_member_id BIGINT UNSIGNED NULL COMMENT '더미계정을 생성한 관리자; 일반 가입은 NULL',
    deleted_at DATETIME(6) NULL COMMENT '탈퇴 시각',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '가입 시각',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '최근 수정 시각',
    PRIMARY KEY (id),
    UNIQUE KEY uq_members_email (email),
    UNIQUE KEY uq_members_nickname (nickname),
    KEY idx_members_dummy_level_status (is_dummy, member_level, status, id),
    KEY idx_members_creator (created_by_member_id),
    KEY idx_members_country (country_code, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='회원, 권한, 국가 및 현재 하트 잔액';

CREATE TABLE member_social_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '소셜 연결 ID',
    member_id BIGINT UNSIGNED NOT NULL COMMENT '연결 회원 ID; 더미 연결 금지는 애플리케이션에서 검사',
    provider VARCHAR(16) NOT NULL COMMENT '현재 google만 허용',
    provider_user_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Google sub; 이메일이 아닌 대소문자 구분 외부 식별값',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '연결 시각',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '최근 수정 시각',
    PRIMARY KEY (id),
    UNIQUE KEY uq_social_provider_user (provider, provider_user_id),
    UNIQUE KEY uq_social_member_provider (member_id, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='회원별 Google 계정 연결; 인증 토큰 원문 저장 금지';

CREATE TABLE member_login_histories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '로그인 이력 ID',
    member_id BIGINT UNSIGNED NULL COMMENT '식별한 회원 ID; 미등록 계정 등 식별 불가 실패는 NULL',
    login_type VARCHAR(16) NOT NULL COMMENT 'password 또는 google',
    result VARCHAR(16) NOT NULL COMMENT 'success 또는 failed',
    ip_address VARCHAR(45) NULL COMMENT '신뢰된 프록시 정책으로 확인한 IPv4/IPv6; 확인 불가 시 NULL',
    user_agent VARCHAR(1024) NULL COMMENT '길이를 제한한 User-Agent; 비밀번호/토큰/세션 ID 저장 금지',
    failure_reason VARCHAR(64) NULL COMMENT '실패 내부 코드; 성공 시 NULL, 상세 오류 원문 금지',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '로그인 시도 결과 기록 시각',
    PRIMARY KEY (id),
    KEY idx_login_member_time (member_id, created_at, id),
    KEY idx_login_ip_time (ip_address, created_at, id),
    KEY idx_login_time (created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='일반회원/관리자/더미의 직접 로그인 성공 및 실패 이력';

CREATE TABLE password_reset_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '비밀번호 재설정 요청 ID',
    member_id BIGINT UNSIGNED NOT NULL COMMENT '재설정 대상 회원 ID',
    token_hash BINARY(32) NOT NULL COMMENT '무작위 재설정 토큰의 SHA-256 바이너리 해시; 원문 저장 금지',
    expires_at DATETIME(6) NOT NULL COMMENT '만료 시각; 재발급/회원정보 변경 시 앞당겨 무효화 가능',
    used_at DATETIME(6) NULL COMMENT '실제 비밀번호 변경 완료 시각; 미사용은 NULL',
    requested_ip VARCHAR(45) NULL COMMENT '요청 IPv4/IPv6; 확인 불가 시 NULL',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '발급 시각',
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_reset_hash (token_hash),
    KEY idx_password_reset_member (member_id, used_at, expires_at),
    KEY idx_password_reset_expiry (expires_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='만료시간이 있는 일회용 비밀번호 재설정 토큰 해시';

CREATE TABLE idols (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '개인 또는 그룹 ID',
    type VARCHAR(16) NOT NULL COMMENT 'individual 개인, group 그룹',
    gender VARCHAR(8) NOT NULL COMMENT 'male 남자/남자그룹, female 여자/여자그룹',
    name VARCHAR(200) NOT NULL COMMENT '공식 표시 이름; 동명이인 허용',
    status VARCHAR(16) NOT NULL DEFAULT 'active' COMMENT 'active 또는 inactive',
    view_count BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '누적 조회수; 랭킹 점수와 무관',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '등록 승인으로 생성된 시각',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '정보 수정 시각; 조회수 증가 시 유지',
    PRIMARY KEY (id),
    KEY idx_idols_category (type, gender, status, id),
    KEY idx_idols_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='개인 아이돌 및 그룹; 생성 경로는 등록 신청 승인으로 통일';

CREATE TABLE idol_group_members (
    individual_id BIGINT UNSIGNED NOT NULL COMMENT 'type=individual인 idols ID',
    group_id BIGINT UNSIGNED NOT NULL COMMENT 'type=group인 idols ID',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '소속 연결 시각',
    PRIMARY KEY (individual_id, group_id),
    KEY idx_group_members_group (group_id, individual_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='개인과 그룹의 다대다 소속; 유형/성별 일치는 애플리케이션 검사';

CREATE TABLE idol_votes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '완료된 투표 ID',
    member_id BIGINT UNSIGNED NOT NULL COMMENT '직접 로그인해 하트를 사용한 회원',
    idol_id BIGINT UNSIGNED NOT NULL COMMENT '투표 대상 개인 또는 그룹',
    heart_amount BIGINT UNSIGNED NOT NULL COMMENT '실제 사용한 하트 수; 랭킹은 대상별 합계',
    request_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '회원별 동일 투표 요청 재전송 식별키',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '투표 완료 시각',
    PRIMARY KEY (id),
    UNIQUE KEY uq_votes_member_request (member_id, request_key),
    KEY idx_votes_idol_time (idol_id, created_at, id),
    KEY idx_votes_member_time (member_id, created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='수정/삭제하지 않는 완료 투표; 잔액 차감 및 이력과 원자적으로 생성';

CREATE TABLE heart_histories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '하트 증감 이력 ID',
    member_id BIGINT UNSIGNED NOT NULL COMMENT '하트 잔액 대상 회원',
    amount BIGINT NOT NULL COMMENT '양수 지급, 음수 차감; 0 금지',
    balance_after BIGINT UNSIGNED NOT NULL COMMENT '해당 처리 직후 잔액',
    reason VARCHAR(32) NOT NULL COMMENT 'signup_reward/ad_reward/event_reward/comment_reward/vote/admin_adjustment/comment_reward_reversal',
    vote_id BIGINT UNSIGNED NULL COMMENT 'vote일 때만 연결되는 투표 ID',
    comment_id BIGINT UNSIGNED NULL COMMENT '댓글 보상 및 보상 회수 대상 댓글 ID',
    reversal_of_history_id BIGINT UNSIGNED NULL COMMENT '전액 회수 대상 원본 댓글 보상 이력 ID; 원본당 1회 회수',
    operation_key VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '회원별 지급/차감 사건의 고정 중복 방지 키',
    actor_member_id BIGINT UNSIGNED NULL COMMENT '실제 작업자; 시스템 지급/회수는 NULL',
    memo VARCHAR(1000) NULL COMMENT '관리자 조정 또는 보상 회수 사유',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '증감 처리 시각',
    PRIMARY KEY (id),
    UNIQUE KEY uq_hearts_vote (vote_id),
    UNIQUE KEY uq_hearts_member_operation (member_id, operation_key),
    UNIQUE KEY uq_hearts_reversal (reversal_of_history_id),
    KEY idx_hearts_member_id (member_id, id),
    KEY idx_hearts_member_reason_time (member_id, reason, created_at, id),
    KEY idx_hearts_actor (actor_member_id, id),
    KEY idx_hearts_comment (comment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='추가 기록 방식의 하트 원장; 기존 이력 변경/삭제는 애플리케이션에서 금지';

CREATE TABLE fan_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '팬포스트 ID',
    idol_id BIGINT UNSIGNED NOT NULL COMMENT '대상 개인 또는 그룹',
    member_id BIGINT UNSIGNED NOT NULL COMMENT '공개 작성자',
    actor_member_id BIGINT UNSIGNED NOT NULL COMMENT '최초 실제 로그인 작업자; 수정 시 덮어쓰지 않음',
    image_path VARCHAR(1024) NULL COMMENT '이미지 1장 저장 경로',
    content TEXT NULL COMMENT '공개 본문',
    status VARCHAR(16) NOT NULL DEFAULT 'published' COMMENT 'published 또는 hidden',
    view_count BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '조회수; 좋아요/랭킹과 독립',
    deleted_at DATETIME(6) NULL COMMENT '삭제 시각',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '작성 또는 신청 승인 시각',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '본문/상태 수정 시각; 조회수 증가 시 유지',
    PRIMARY KEY (id),
    KEY idx_posts_idol_feed (idol_id, status, deleted_at, created_at, id),
    KEY idx_posts_member_feed (member_id, deleted_at, created_at, id),
    KEY idx_posts_actor_time (actor_member_id, created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='아이돌별 팬포스트; 이미지 또는 본문 중 하나 이상 필수';

CREATE TABLE fan_post_likes (
    fan_post_id BIGINT UNSIGNED NOT NULL COMMENT '좋아요 대상 게시물',
    member_id BIGINT UNSIGNED NOT NULL COMMENT '직접 로그인한 회원',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '좋아요 시각',
    PRIMARY KEY (fan_post_id, member_id),
    KEY idx_likes_member (member_id, fan_post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='회원별 팬포스트 좋아요; 하트/투표와 무관, 취소 시 관계 삭제';

CREATE TABLE fan_post_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '댓글 ID',
    fan_post_id BIGINT UNSIGNED NOT NULL COMMENT '부모 게시물',
    member_id BIGINT UNSIGNED NOT NULL COMMENT '공개 댓글 작성자',
    actor_member_id BIGINT UNSIGNED NOT NULL COMMENT '최초 실제 로그인 작업자',
    request_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '실제 작업자 기준 댓글 등록 요청 중복 방지 키; 동일 요청 재시도는 기존 댓글 반환',
    content TEXT NOT NULL COMMENT '댓글 본문; 대댓글은 지원하지 않음',
    status VARCHAR(16) NOT NULL DEFAULT 'published' COMMENT 'published 또는 hidden',
    deleted_at DATETIME(6) NULL COMMENT '삭제 시각',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '댓글 작성 시각',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '최근 수정 시각',
    PRIMARY KEY (id),
    UNIQUE KEY uq_comments_actor_request (actor_member_id, request_key),
    KEY idx_comments_post_feed (fan_post_id, status, deleted_at, created_at, id),
    KEY idx_comments_member_feed (member_id, deleted_at, created_at, id),
    KEY idx_comments_actor_time (actor_member_id, created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='팬포스트 댓글; 보상 금액과 지급 여부는 하트 이력에서 확인';

CREATE TABLE idol_registration_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '아이돌 등록 신청 ID',
    member_id BIGINT UNSIGNED NOT NULL COMMENT '공개 신청자; 관리자 등록 시 선택한 더미',
    actor_member_id BIGINT UNSIGNED NOT NULL COMMENT '실제 신청 작업자; 관리자 등록 시 관리자',
    type VARCHAR(16) NOT NULL COMMENT 'individual 또는 group',
    gender VARCHAR(8) NOT NULL COMMENT 'male 또는 female',
    name VARCHAR(200) NOT NULL COMMENT '신청 아이돌 이름',
    group_id BIGINT UNSIGNED NULL COMMENT '개인 신청의 기존 소속 그룹; 그룹 신청은 NULL',
    image_path VARCHAR(1024) NOT NULL COMMENT '필수 신청 이미지; 승인 팬포스트에 그대로 사용',
    content TEXT NOT NULL COMMENT '필수 공개 본문; 승인 팬포스트에 그대로 사용',
    reference_url VARCHAR(2048) NULL COMMENT '아이돌 확인용 참고 주소',
    note TEXT NULL COMMENT '검토자에게 전달할 비공개 설명',
    request_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '실제 작업자 기준 동일 신청 재전송 식별키',
    status VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected',
    approved_idol_id BIGINT UNSIGNED NULL COMMENT '승인으로 생성하거나 연결한 아이돌',
    approved_fan_post_id BIGINT UNSIGNED NULL COMMENT '승인 과정에서 생성한 팬포스트',
    reviewed_by BIGINT UNSIGNED NULL COMMENT '승인/반려 관리자; 원래 작업자와 별개',
    reviewed_at DATETIME(6) NULL COMMENT '승인 또는 반려 시각',
    review_note TEXT NULL COMMENT '검토 기록; 반려 시 필수',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '신청 시각',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '최근 처리 시각',
    PRIMARY KEY (id),
    UNIQUE KEY uq_requests_actor_key (actor_member_id, request_key),
    UNIQUE KEY uq_requests_post (approved_fan_post_id),
    KEY idx_requests_status_time (status, created_at, id),
    KEY idx_requests_member_time (member_id, created_at, id),
    KEY idx_requests_actor_time (actor_member_id, created_at, id),
    KEY idx_requests_group (group_id),
    KEY idx_requests_idol (approved_idol_id),
    KEY idx_requests_reviewer (reviewed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='모든 아이돌 등록의 단일 진입점; 승인 결과와 실제 작업자 보존';

-- 모든 테이블 생성 후 FK 추가. 기본 NO ACTION은 InnoDB에서 즉시 RESTRICT와 동일하다.
ALTER TABLE members
    ADD CONSTRAINT fk_members_creator FOREIGN KEY (created_by_member_id) REFERENCES members (id);

ALTER TABLE member_social_accounts
    ADD CONSTRAINT fk_social_member FOREIGN KEY (member_id) REFERENCES members (id);

ALTER TABLE member_login_histories
    ADD CONSTRAINT fk_login_member FOREIGN KEY (member_id) REFERENCES members (id);

ALTER TABLE password_reset_tokens
    ADD CONSTRAINT fk_password_reset_member FOREIGN KEY (member_id) REFERENCES members (id);

ALTER TABLE idol_group_members
    ADD CONSTRAINT fk_group_members_individual FOREIGN KEY (individual_id) REFERENCES idols (id),
    ADD CONSTRAINT fk_group_members_group FOREIGN KEY (group_id) REFERENCES idols (id);

ALTER TABLE idol_votes
    ADD CONSTRAINT fk_votes_member FOREIGN KEY (member_id) REFERENCES members (id),
    ADD CONSTRAINT fk_votes_idol FOREIGN KEY (idol_id) REFERENCES idols (id);

ALTER TABLE heart_histories
    ADD CONSTRAINT fk_hearts_member FOREIGN KEY (member_id) REFERENCES members (id),
    ADD CONSTRAINT fk_hearts_vote FOREIGN KEY (vote_id) REFERENCES idol_votes (id),
    ADD CONSTRAINT fk_hearts_comment FOREIGN KEY (comment_id) REFERENCES fan_post_comments (id),
    ADD CONSTRAINT fk_hearts_reversal FOREIGN KEY (reversal_of_history_id) REFERENCES heart_histories (id),
    ADD CONSTRAINT fk_hearts_actor FOREIGN KEY (actor_member_id) REFERENCES members (id);

ALTER TABLE fan_posts
    ADD CONSTRAINT fk_posts_idol FOREIGN KEY (idol_id) REFERENCES idols (id),
    ADD CONSTRAINT fk_posts_member FOREIGN KEY (member_id) REFERENCES members (id),
    ADD CONSTRAINT fk_posts_actor FOREIGN KEY (actor_member_id) REFERENCES members (id);

ALTER TABLE fan_post_likes
    ADD CONSTRAINT fk_likes_post FOREIGN KEY (fan_post_id) REFERENCES fan_posts (id),
    ADD CONSTRAINT fk_likes_member FOREIGN KEY (member_id) REFERENCES members (id);

ALTER TABLE fan_post_comments
    ADD CONSTRAINT fk_comments_post FOREIGN KEY (fan_post_id) REFERENCES fan_posts (id),
    ADD CONSTRAINT fk_comments_member FOREIGN KEY (member_id) REFERENCES members (id),
    ADD CONSTRAINT fk_comments_actor FOREIGN KEY (actor_member_id) REFERENCES members (id);

ALTER TABLE idol_registration_requests
    ADD CONSTRAINT fk_requests_member FOREIGN KEY (member_id) REFERENCES members (id),
    ADD CONSTRAINT fk_requests_actor FOREIGN KEY (actor_member_id) REFERENCES members (id),
    ADD CONSTRAINT fk_requests_group FOREIGN KEY (group_id) REFERENCES idols (id),
    ADD CONSTRAINT fk_requests_idol FOREIGN KEY (approved_idol_id) REFERENCES idols (id),
    ADD CONSTRAINT fk_requests_post FOREIGN KEY (approved_fan_post_id) REFERENCES fan_posts (id),
    ADD CONSTRAINT fk_requests_reviewer FOREIGN KEY (reviewed_by) REFERENCES members (id);

-- CHECK 제약은 사용하지 않으며 업무 값 검증은 Laravel 애플리케이션에서 수행한다.
-- 컬럼 COMMENT의 허용값/필수 조건은 업무 규칙이다. DB는 PK/FK/UNIQUE/NOT NULL/타입/DEFAULT/INDEX를 유지한다.
-- member_level/is_dummy/status/type/gender/provider/login_type/result/reason 허용값을 검사한다.
-- 비탈퇴 회원 이메일/닉네임, 더미 생성자/비밀번호, 활성 회원 국가 및 탈퇴 상태 일치를 검사한다.
-- 로그인 성공은 회원 ID 필수/실패 사유 NULL, 실패는 내부 사유 코드 필수이다.
-- 이름/식별키/댓글은 빈 값 금지. 일반 팬포스트는 이미지 또는 본문 필수, 신청은 둘 다 필수이다.
-- 그룹 신청의 group_id는 NULL이며, 소속의 개인 ID와 그룹 ID는 달라야 한다.
-- 투표량>0, 하트 amount!=0 및 reason별 부호/관계/작업자/조정·회수 사유를 검사한다.
-- vote만 vote_id 필수 및 작업자=회원, 댓글 보상/회수만 comment_id 필수, 회수만 원본 ID 필수이다.
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
--    팬포스트는 신청의 image_path/content/member_id/actor_member_id를 그대로 사용한다.
--    관리자 즉시 등록은 신청 생성까지 같은 트랜잭션. 파일 저장 자체는 DB 롤백 대상이 아니다.
-- 7. 모든 하트 처리에서 회원 행을 먼저 잠근다. 잔액=이력 합계, 투표량=-차감량,
--    투표/댓글/원본 지급의 회원 일치 및 투표당 차감 이력 존재는 같은 트랜잭션에서 보장한다.
--    amount가 signed BIGINT이므로 1회 지급/차감과 투표량은 그 표현 범위 내로 검증한다.
-- 8. operation_key는 검증된 지급 사건에서 고정 생성한다. 광고 사건은 수령 회원까지 검증한다.
--    동일 키 재시도는 기존 결과 반환, 금액/대상/사유가 다르면 거절한다.
-- 9. 댓글 보상은 level=1, is_dummy=0, active, deleted_at IS NULL 및 member=actor만 허용.
--    댓글 생성/잔액 증가/이력은 한 트랜잭션, 키는 comment_reward:{comment_id}로 고정한다.
--    금액/일일 횟수/기준 시간대는 운영 설정. 회원 잠금 아래 지급 횟수를 세며 회수해도 횟수 유지.
--    같은 댓글 ID 재지급 방지와 별도로 반복 댓글/스팸/요청 재전송을 검사한다.
-- 10. 회수는 원본 comment_reward와 동일 회원/댓글, 반대 전액인지 검사한다.
--     키는 comment_reward_reversal:{원본_하트이력_id}. 잔액 부족 시 회수를 실패 처리한다.
--     원본 이력과 완료 투표는 수정/삭제하지 않으며 자기 참조 회수도 금지한다.
-- 11. 랭킹은 idol_votes.heart_amount 합계. 조회수/좋아요/보유 잔액은 점수에서 제외한다.
--     조회수는 세션별 단시간 중복과 동시 요청을 제한하고 원자적으로 증가시킨다.
-- 12. 탈퇴는 level=0/inactive/deleted_at과 개인정보 정리, Google 연결 삭제 및 토큰 무효화로 처리.
--     잔액/이력은 보존하며 로그/IP와 만료 토큰의 보관 기간은 별도 운영 정책으로 관리한다.
