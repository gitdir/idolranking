-- Add optional group profile fields to registration requests and approved idols.
-- Individual idol records keep both fields NULL; leader is intentionally free text.
ALTER TABLE idols
    ADD COLUMN genre VARCHAR(200) NULL COMMENT 'Group genre; NULL for individuals' AFTER position,
    ADD COLUMN leader VARCHAR(200) NULL COMMENT 'Leader name as free text; NULL for individuals' AFTER genre;

ALTER TABLE idol_registration_requests
    ADD COLUMN genre VARCHAR(200) NULL COMMENT 'Requested group genre; NULL for individuals' AFTER position,
    ADD COLUMN leader VARCHAR(200) NULL COMMENT 'Requested leader name as free text; NULL for individuals' AFTER genre;
