ALTER TABLE idol_registration_requests
    ADD COLUMN english_name VARCHAR(200) NULL AFTER name,
    ADD COLUMN birth_date DATE NULL AFTER group_idx,
    ADD COLUMN nationality VARCHAR(100) NULL AFTER birth_date,
    ADD COLUMN height_cm DECIMAL(5,2) NULL AFTER nationality,
    ADD COLUMN weight_kg DECIMAL(5,2) NULL AFTER height_cm,
    ADD COLUMN blood_type VARCHAR(8) NULL AFTER weight_kg,
    ADD COLUMN agency VARCHAR(200) NULL AFTER blood_type,
    ADD COLUMN debut_date DATE NULL AFTER agency,
    ADD COLUMN position VARCHAR(200) NULL AFTER debut_date,
    ADD COLUMN sns VARCHAR(1000) NULL AFTER position;

UPDATE idol_registration_requests
SET english_name = name
WHERE english_name IS NULL OR english_name = '';

ALTER TABLE idol_registration_requests
    MODIFY COLUMN english_name VARCHAR(200) NOT NULL;
