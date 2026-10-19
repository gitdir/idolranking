ALTER TABLE member_access_logs
    MODIFY COLUMN ip_address VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT 'NULL for privacy-conscious visitor tracking; legacy values are retained until log expiry';
