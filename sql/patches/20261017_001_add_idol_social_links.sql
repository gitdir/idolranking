-- Store up to three validated social URLs as a JSON array while retaining legacy sns values.
ALTER TABLE idols
    ADD COLUMN sns_links TEXT NULL AFTER sns;

ALTER TABLE idol_registration_requests
    ADD COLUMN sns_links TEXT NULL AFTER sns;
