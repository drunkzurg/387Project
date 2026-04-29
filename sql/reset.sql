-- reset.sql
-- Drops tables in FK-safe order so you can re-run migrations from scratch.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS revenue_reports;
DROP TABLE IF EXISTS gift_shop_redemptions;
DROP TABLE IF EXISTS ticket_transactions;
DROP TABLE IF EXISTS ticket_accounts;
DROP TABLE IF EXISTS gift_shop_items;
DROP TABLE IF EXISTS attendee_sessions;
DROP TABLE IF EXISTS attendees;
DROP TABLE IF EXISTS hr_action_logs;
DROP TABLE IF EXISTS employee_sick_requests;
DROP TABLE IF EXISTS employee_transfers;
DROP TABLE IF EXISTS employee_hours;
DROP TABLE IF EXISTS employee_shifts;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;
