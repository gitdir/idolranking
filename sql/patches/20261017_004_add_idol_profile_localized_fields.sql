-- Extend approved and requested multilingual profile data without changing existing text.
-- Legacy nationality values are intentionally not converted; normalize only verified values.

ALTER TABLE idols
    ADD COLUMN nationality_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NULL
        COMMENT 'ISO 3166-1 alpha-2; NULL when unknown or not normalized' AFTER nationality;

ALTER TABLE idol_registration_requests
    ADD COLUMN nationality_code CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NULL
        COMMENT 'ISO 3166-1 alpha-2; NULL when unknown or not normalized' AFTER nationality;

ALTER TABLE idol_localizations
    ADD COLUMN agency VARCHAR(200) NULL AFTER description,
    ADD COLUMN position VARCHAR(200) NULL AFTER agency,
    ADD COLUMN genre VARCHAR(200) NULL AFTER position,
    ADD COLUMN leader VARCHAR(200) NULL AFTER genre;

ALTER TABLE idol_registration_request_localizations
    ADD COLUMN agency VARCHAR(200) NULL AFTER description,
    ADD COLUMN position VARCHAR(200) NULL AFTER agency,
    ADD COLUMN genre VARCHAR(200) NULL AFTER position,
    ADD COLUMN leader VARCHAR(200) NULL AFTER genre;
