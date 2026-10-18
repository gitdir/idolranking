-- Adds optional source-language metadata without guessing the language of existing posts.
ALTER TABLE fan_posts
    ADD COLUMN language_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NULL
        COMMENT 'ISO 639-1 original post language; NULL for posts without a recorded language'
        AFTER media_type;
