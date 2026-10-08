-- Add an opaque, case-sensitive public identifier while preserving members.idx for internal relations.
ALTER TABLE members
    ADD COLUMN member_code VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL
        COMMENT 'Public immutable member identifier' AFTER idx,
    ADD UNIQUE KEY uq_members_member_code (member_code);

DELIMITER $$

CREATE PROCEDURE backfill_members_member_code()
BEGIN
    DECLARE candidate VARCHAR(12);

    WHILE EXISTS (SELECT 1 FROM members WHERE member_code IS NULL) DO
        SET candidate = UPPER(HEX(RANDOM_BYTES(6)));

        IF NOT EXISTS (SELECT 1 FROM members WHERE member_code = candidate) THEN
            UPDATE members
            SET member_code = candidate
            WHERE member_code IS NULL
            ORDER BY idx
            LIMIT 1;
        END IF;
    END WHILE;
END$$

DELIMITER ;

CALL backfill_members_member_code();
DROP PROCEDURE backfill_members_member_code;

ALTER TABLE members
    MODIFY COLUMN member_code VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
        COMMENT 'Public immutable member identifier';
