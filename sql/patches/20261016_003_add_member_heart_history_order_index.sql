ALTER TABLE heart_histories
    ADD INDEX idx_hearts_member_idx_created_at_idx (member_idx, created_at, idx);
