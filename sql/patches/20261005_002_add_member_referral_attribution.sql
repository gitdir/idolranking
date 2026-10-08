ALTER TABLE members
    ADD COLUMN referred_by_member_idx BIGINT UNSIGNED NULL
        COMMENT 'Inviting member for this account, if registered through a referral'
        AFTER created_by_member_idx,
    ADD KEY idx_members_referred_by_member_idx (referred_by_member_idx),
    ADD CONSTRAINT fk_members_referred_by_member_idx
        FOREIGN KEY (referred_by_member_idx) REFERENCES members (idx);
