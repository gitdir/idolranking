-- Preserve the effective dummy account as activity owner while auditing the real Admin actor.
-- Existing rows predate delegated activity and retain NULL actor attribution.
ALTER TABLE idol_votes
    ADD COLUMN actor_member_idx BIGINT UNSIGNED NULL AFTER member_idx,
    ADD KEY idx_votes_actor_member_idx_created_at_idx (actor_member_idx, created_at, idx),
    ADD CONSTRAINT fk_votes_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx);

ALTER TABLE fan_post_likes
    ADD COLUMN actor_member_idx BIGINT UNSIGNED NULL AFTER member_idx,
    ADD KEY idx_likes_actor_member_idx_created_at_idx (actor_member_idx, created_at),
    ADD CONSTRAINT fk_likes_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx);

ALTER TABLE fan_post_reports
    ADD COLUMN actor_member_idx BIGINT UNSIGNED NULL AFTER member_idx,
    ADD KEY idx_fan_post_reports_actor_member_idx_created_at_idx (actor_member_idx, created_at, idx),
    ADD CONSTRAINT fk_fan_post_reports_actor_member_idx FOREIGN KEY (actor_member_idx) REFERENCES members (idx);
