ALTER TABLE idol_votes
    ADD COLUMN country_code_at_vote CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NULL
        COMMENT 'Voting member country at vote time; NULL when unknown'
        AFTER heart_amount;
