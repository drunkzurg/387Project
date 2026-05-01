-- Upgrade: remove attendees.created_at (redundant with verified_at for support-created members).
-- Run once on databases that were created from an older 000_run_all.sql that included this column.
ALTER TABLE attendees DROP COLUMN created_at;
