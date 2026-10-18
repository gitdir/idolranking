-- Remove the development-only free-text nationality fields.
-- Apply once after 20261017_004_add_idol_profile_localized_fields.sql.
-- Existing values in these columns are discarded; reviewed country codes remain unchanged.

ALTER TABLE idols
    DROP COLUMN nationality;

ALTER TABLE idol_registration_requests
    DROP COLUMN nationality;
