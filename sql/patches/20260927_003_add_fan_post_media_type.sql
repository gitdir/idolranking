ALTER TABLE fan_posts
    ADD COLUMN media_type VARCHAR(16) NULL AFTER actor_member_idx;

UPDATE fan_posts
SET media_type = CASE
    WHEN youtube_video_id IS NOT NULL THEN 'youtube'
    WHEN image_path IS NOT NULL THEN 'image'
    ELSE 'text'
END;

ALTER TABLE fan_posts
    MODIFY COLUMN media_type VARCHAR(16) NOT NULL DEFAULT 'text' COMMENT 'text, image, or youtube';
