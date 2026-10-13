ALTER TABLE members
    ADD COLUMN profile_image_path VARCHAR(1024) NULL COMMENT '회원 프로필 이미지 공개 저장 경로' AFTER country_code;
