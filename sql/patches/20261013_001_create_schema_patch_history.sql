-- Run once on an existing installation that predates schema_patch_history.
-- Fresh installations already receive this table from deploy/mariadb11_schema.sql.
CREATE TABLE schema_patch_history (
    idx BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    patch_name VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    applied_by VARCHAR(100) NULL,
    notes VARCHAR(1000) NULL,
    PRIMARY KEY (idx),
    UNIQUE KEY uq_schema_patch_history_patch_name (patch_name),
    KEY idx_schema_patch_history_applied_at (applied_at, idx)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sequential SQL patches successfully applied to this database';
