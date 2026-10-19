ALTER TABLE fan_posts
    MODIFY COLUMN status VARCHAR(16) NOT NULL DEFAULT 'published'
        COMMENT 'published, hidden, queued, or cancelled',
    ADD COLUMN queued_at DATETIME(6) NULL COMMENT '관리자 분산 발행 대기열 진입 시각' AFTER status,
    ADD COLUMN published_at DATETIME(6) NULL COMMENT '실제 공개 시각; 기존 글은 created_at으로 대체' AFTER queued_at,
    ADD KEY idx_posts_status_deleted_at_queued_at_idx (status, deleted_at, queued_at, idx);
