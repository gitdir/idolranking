ALTER TABLE visitor_daily_stats
    ADD COLUMN page_views BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER stat_date;

CREATE TABLE visitor_daily_uniques (
    stat_date DATE NOT NULL,
    visitor_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Random first-party visitor identifier',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (stat_date, visitor_id),
    KEY idx_visitor_daily_uniques_visitor_date (visitor_id, stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Long-term visitor/day identities for daily and period UV';

-- Backfill the detailed-log window where visitor identities are available.
INSERT IGNORE INTO visitor_daily_uniques (stat_date, visitor_id, created_at)
SELECT DATE(created_at), visitor_id, MIN(created_at)
FROM member_access_logs
GROUP BY DATE(created_at), visitor_id;

UPDATE visitor_daily_stats AS stats
JOIN (
    SELECT DATE(created_at) AS stat_date, COUNT(*) AS page_views
    FROM member_access_logs
    GROUP BY DATE(created_at)
) AS access_counts ON access_counts.stat_date = stats.stat_date
SET stats.page_views = access_counts.page_views;
