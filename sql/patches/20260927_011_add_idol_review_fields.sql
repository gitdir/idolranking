ALTER TABLE idols
    ADD COLUMN english_name VARCHAR(200) NULL AFTER name,
    ADD COLUMN agency VARCHAR(200) NULL AFTER english_name,
    ADD COLUMN debut_date DATE NULL AFTER agency,
    ADD COLUMN birth_date DATE NULL AFTER debut_date,
    ADD COLUMN nationality VARCHAR(100) NULL AFTER birth_date,
    ADD COLUMN height_cm DECIMAL(5,2) NULL AFTER nationality,
    ADD COLUMN weight_kg DECIMAL(5,2) NULL AFTER height_cm,
    ADD COLUMN blood_type VARCHAR(8) NULL AFTER weight_kg,
    ADD COLUMN position VARCHAR(200) NULL AFTER blood_type,
    ADD COLUMN sns VARCHAR(1000) NULL AFTER position,
    ADD COLUMN image_path VARCHAR(1024) NULL AFTER sns,
    ADD COLUMN content TEXT NULL AFTER image_path;

ALTER TABLE idol_registration_requests
    ADD COLUMN rejection_reason VARCHAR(1000) NULL AFTER review_note;
