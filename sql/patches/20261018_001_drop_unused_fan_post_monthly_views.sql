-- Preconditions: verify the application no longer references this table and
-- SELECT COUNT(*) FROM fan_post_monthly_views returns 0 immediately before use.
-- The supported database-operation deployment path enforces the empty-table check.
-- Keep this statement guarded by that preflight; it permanently removes the table.
DROP TABLE `fan_post_monthly_views`;
