-- 001_seed_sample.sql
-- One-week demo data for the ticket-based arcade management system.
-- Assumes sql/migrations/000_run_all.sql has already been applied to an empty schema.

-- Common password for all seed users is: password
-- bcrypt hash generated via PHP password_hash('password', PASSWORD_DEFAULT)
SET @pw := '$2y$12$3xyBZgPV9pqcI.h0LTf9X.2BmJd1M47oLNNFGRDz5abHXLxLV6.Xm';

-- Users kept for debug toolbar quick-login compatibility.
INSERT INTO users (name, email, password_hash, role, pending_approval)
VALUES
  ('System Admin', 'admin@arcade.local', @pw, 'sys_admin', 0),
  ('Owner One', 'owner@arcade.local', @pw, 'owner', 0),
  ('HR One', 'hr@arcade.local', @pw, 'hr', 0),
  ('Arcade Host', 'employee@arcade.local', @pw, 'employee', 0),
  ('Gift Shop Clerk', 'giftshop@arcade.local', @pw, 'employee', 0),
  ('Support Agent', 'support@arcade.local', @pw, 'employee', 0),
  ('Laser Lead', 'laser@arcade.local', @pw, 'employee', 0),
  ('Pinball Pro', 'pinball@arcade.local', @pw, 'employee', 0),
  ('Racing Ranger', 'racing@arcade.local', @pw, 'employee', 0),
  ('Rhythm Runner', 'rhythm@arcade.local', @pw, 'employee', 0),
  ('Prize Tower Tech', 'prizetower@arcade.local', @pw, 'employee', 0),
  ('Evening Floater', 'floater@arcade.local', @pw, 'employee', 0),
  ('Pending Owner Request', 'pending.owner@arcade.local', @pw, 'owner', 1),
  ('Pending Employee Request', 'pending.employee@arcade.local', @pw, 'employee', 1),
  ('Pending HR Request', 'pending.hr@arcade.local', @pw, 'hr', 1);

-- Departments: a richer arcade floor with multiple play areas.
INSERT INTO departments (name, department_type, entrance_fee_tickets, capacity, operating_status, description)
VALUES
  ('Retro Arcade', 'play_area', 25, 8, 'active', 'Classic redemption games and family play.'),
  ('VR Arena', 'play_area', 60, 4, 'active', 'Premium headset experience with high-ticket payouts.'),
  ('Claw Corner', 'play_area', 15, 6, 'out_of_order', 'Compact claw-machine bay.'),
  ('Laser Maze', 'play_area', 35, 10, 'active', 'Timed laser obstacle challenge.'),
  ('Pinball Palace', 'play_area', 20, 8, 'active', 'Modern and vintage pinball bank.'),
  ('Racing Alley', 'play_area', 30, 6, 'active', 'Linked racing cabinets.'),
  ('Rhythm Stage', 'play_area', 25, 5, 'out_of_order', 'Dance and rhythm games.'),
  ('Prize Tower', 'play_area', 45, 3, 'active', 'High-risk prize tower challenge.'),
  ('Gift Shop', 'gift_shop', 0, 0, 'active', 'Prize counter and redemption desk.'),
  ('Customer Support', 'customer_support', 0, 0, 'active', 'Membership verification and claim review.');

SET @retro_department := (SELECT department_id FROM departments WHERE name = 'Retro Arcade');
SET @vr_department := (SELECT department_id FROM departments WHERE name = 'VR Arena');
SET @claw_department := (SELECT department_id FROM departments WHERE name = 'Claw Corner');
SET @laser_department := (SELECT department_id FROM departments WHERE name = 'Laser Maze');
SET @pinball_department := (SELECT department_id FROM departments WHERE name = 'Pinball Palace');
SET @racing_department := (SELECT department_id FROM departments WHERE name = 'Racing Alley');
SET @rhythm_department := (SELECT department_id FROM departments WHERE name = 'Rhythm Stage');
SET @tower_department := (SELECT department_id FROM departments WHERE name = 'Prize Tower');
SET @gift_shop_department := (SELECT department_id FROM departments WHERE name = 'Gift Shop');
SET @support_department := (SELECT department_id FROM departments WHERE name = 'Customer Support');

-- Employees.
INSERT INTO employees (user_id, name, department_id, hourly_wage, status)
VALUES
  ((SELECT user_id FROM users WHERE email = 'owner@arcade.local'), 'Owner One', @gift_shop_department, 25.00, 'active'),
  ((SELECT user_id FROM users WHERE email = 'hr@arcade.local'), 'HR One', @support_department, 22.00, 'active'),
  ((SELECT user_id FROM users WHERE email = 'employee@arcade.local'), 'Arcade Host', @retro_department, 16.50, 'active'),
  ((SELECT user_id FROM users WHERE email = 'giftshop@arcade.local'), 'Gift Shop Clerk', @gift_shop_department, 16.00, 'active'),
  ((SELECT user_id FROM users WHERE email = 'support@arcade.local'), 'Support Agent', @support_department, 17.00, 'active'),
  ((SELECT user_id FROM users WHERE email = 'laser@arcade.local'), 'Laser Lead', @laser_department, 17.25, 'active'),
  ((SELECT user_id FROM users WHERE email = 'pinball@arcade.local'), 'Pinball Pro', @pinball_department, 16.75, 'active'),
  ((SELECT user_id FROM users WHERE email = 'racing@arcade.local'), 'Racing Ranger', @racing_department, 16.80, 'active'),
  ((SELECT user_id FROM users WHERE email = 'rhythm@arcade.local'), 'Rhythm Runner', @rhythm_department, 16.25, 'active'),
  ((SELECT user_id FROM users WHERE email = 'prizetower@arcade.local'), 'Prize Tower Tech', @tower_department, 18.00, 'active'),
  ((SELECT user_id FROM users WHERE email = 'floater@arcade.local'), 'Evening Floater', @retro_department, 15.75, 'transferred');

SET @owner_user := (SELECT user_id FROM users WHERE email = 'owner@arcade.local');
SET @hr_user := (SELECT user_id FROM users WHERE email = 'hr@arcade.local');
SET @arcade_user := (SELECT user_id FROM users WHERE email = 'employee@arcade.local');
SET @gift_shop_user := (SELECT user_id FROM users WHERE email = 'giftshop@arcade.local');
SET @support_user := (SELECT user_id FROM users WHERE email = 'support@arcade.local');
SET @laser_user := (SELECT user_id FROM users WHERE email = 'laser@arcade.local');
SET @pinball_user := (SELECT user_id FROM users WHERE email = 'pinball@arcade.local');
SET @racing_user := (SELECT user_id FROM users WHERE email = 'racing@arcade.local');
SET @rhythm_user := (SELECT user_id FROM users WHERE email = 'rhythm@arcade.local');
SET @tower_user := (SELECT user_id FROM users WHERE email = 'prizetower@arcade.local');
SET @floater_user := (SELECT user_id FROM users WHERE email = 'floater@arcade.local');

SET @owner_employee := (SELECT employee_id FROM employees WHERE user_id = @owner_user);
SET @hr_employee := (SELECT employee_id FROM employees WHERE user_id = @hr_user);
SET @arcade_employee := (SELECT employee_id FROM employees WHERE user_id = @arcade_user);
SET @gift_shop_employee := (SELECT employee_id FROM employees WHERE user_id = @gift_shop_user);
SET @support_employee := (SELECT employee_id FROM employees WHERE user_id = @support_user);
SET @laser_employee := (SELECT employee_id FROM employees WHERE user_id = @laser_user);
SET @pinball_employee := (SELECT employee_id FROM employees WHERE user_id = @pinball_user);
SET @racing_employee := (SELECT employee_id FROM employees WHERE user_id = @racing_user);
SET @rhythm_employee := (SELECT employee_id FROM employees WHERE user_id = @rhythm_user);
SET @tower_employee := (SELECT employee_id FROM employees WHERE user_id = @tower_user);
SET @floater_employee := (SELECT employee_id FROM employees WHERE user_id = @floater_user);

-- Live/manual shifts across a week. Open live shifts keep departments staffed.
INSERT INTO employee_shifts (employee_id, start_time, end_time, entry_type)
VALUES
  (@arcade_employee, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 6 DAY), INTERVAL -8 HOUR), 'manual'),
  (@arcade_employee, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 5 DAY), INTERVAL -7 HOUR), 'manual'),
  (@arcade_employee, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 4 DAY), INTERVAL -8 HOUR), 'manual'),
  (@arcade_employee, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 3 DAY), INTERVAL -6 HOUR), 'manual'),
  (@arcade_employee, DATE_SUB(NOW(), INTERVAL 90 MINUTE), NULL, 'live'),
  (@laser_employee, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 6 DAY), INTERVAL -7 HOUR), 'manual'),
  (@laser_employee, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 2 DAY), INTERVAL -8 HOUR), 'manual'),
  (@laser_employee, DATE_SUB(NOW(), INTERVAL 75 MINUTE), NULL, 'live'),
  (@pinball_employee, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 5 DAY), INTERVAL -8 HOUR), 'manual'),
  (@pinball_employee, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 3 DAY), INTERVAL -7 HOUR), 'manual'),
  (@pinball_employee, DATE_SUB(NOW(), INTERVAL 45 MINUTE), NULL, 'live'),
  (@racing_employee, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 4 DAY), INTERVAL -7 HOUR), 'manual'),
  (@racing_employee, DATE_SUB(NOW(), INTERVAL 65 MINUTE), NULL, 'live'),
  (@tower_employee, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 6 DAY), INTERVAL -8 HOUR), 'manual'),
  (@tower_employee, DATE_SUB(NOW(), INTERVAL 50 MINUTE), NULL, 'live'),
  (@gift_shop_employee, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 5 DAY), INTERVAL -8 HOUR), 'manual'),
  (@gift_shop_employee, DATE_SUB(NOW(), INTERVAL 40 MINUTE), NULL, 'live'),
  (@support_employee, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 6 DAY), INTERVAL -7 HOUR), 'manual'),
  (@support_employee, DATE_SUB(NOW(), INTERVAL 55 MINUTE), NULL, 'live'),
  (@rhythm_employee, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 2 DAY), INTERVAL -6 HOUR), 'manual');

-- Sick requests for HR and employee dashboards.
INSERT INTO employee_sick_requests (employee_id, request_date, status, notes, requested_at, reviewed_by_user_id, reviewed_at, review_notes)
VALUES
  (@arcade_employee, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'approved', 'Fever and stayed home.', DATE_SUB(NOW(), INTERVAL 3 DAY), @hr_user, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Approved for one paid sick day.'),
  (@laser_employee, CURDATE(), 'waiting', 'Migraine symptoms this morning.', DATE_SUB(NOW(), INTERVAL 2 HOUR), NULL, NULL, NULL),
  (@gift_shop_employee, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'waiting', 'Doctor appointment and recovery day.', DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL, NULL, NULL),
  (@racing_employee, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'denied', 'Request submitted after completed shift.', DATE_SUB(NOW(), INTERVAL 4 DAY), @hr_user, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Denied because shift was already worked.');

-- HR action log history.
INSERT INTO hr_action_logs (action_type, employee_id, handled_by_user_id, details, created_at)
VALUES
  ('add_employee', @laser_employee, @hr_user, 'Added Laser Lead to Laser Maze.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
  ('update_employee', @floater_employee, @hr_user, 'Transferred Evening Floater to Retro Arcade coverage.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
  ('add_shift', @arcade_employee, @hr_user, 'Backfilled a manual shift for Arcade Host.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  ('sick_request_approved', @arcade_employee, @hr_user, 'Approved sick day for Arcade Host.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
  ('sick_request_denied', @racing_employee, @hr_user, 'Denied sick request after completed shift.', DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Attendees / members.
INSERT INTO attendees (name, email, membership_code, is_member, verified_by_employee_id, verified_at)
VALUES
  ('Alice Member', 'alice.member@example.com', 'MEM-1001', 1, @support_employee, DATE_SUB(NOW(), INTERVAL 6 DAY)),
  ('Casey Claim', 'casey.claim@example.com', 'MEM-1002', 1, @support_employee, DATE_SUB(NOW(), INTERVAL 2 DAY)),
  ('Jordan Jackpot', 'jordan.jackpot@example.com', 'MEM-1003', 1, @support_employee, DATE_SUB(NOW(), INTERVAL 3 DAY)),
  ('Morgan Racer', 'morgan.racer@example.com', 'MEM-1004', 1, @support_employee, DATE_SUB(NOW(), INTERVAL 1 DAY));

SET @alice_attendee := (SELECT attendee_id FROM attendees WHERE membership_code = 'MEM-1001');
SET @casey_attendee := (SELECT attendee_id FROM attendees WHERE membership_code = 'MEM-1002');
SET @jordan_attendee := (SELECT attendee_id FROM attendees WHERE membership_code = 'MEM-1003');
SET @morgan_attendee := (SELECT attendee_id FROM attendees WHERE membership_code = 'MEM-1004');

-- Gift shop items.
INSERT INTO gift_shop_items (name, ticket_price, cost_price, stock, status, category, description)
VALUES
  ('Candy Bar', 10, 0.75, 250, 'active', 'snacks', 'Low-cost snack for quick redemptions.'),
  ('Sticker Pack', 20, 1.50, 120, 'active', 'accessories', 'Popular low-ticket item for walk-ins.'),
  ('Arcade Tumbler', 80, 8.00, 25, 'active', 'merch', 'Reusable tumbler used in redemptions.'),
  ('Mystery Plush', 250, 11.50, 18, 'active', 'plush', 'Medium-tier redemption item.'),
  ('LED Sword', 400, 17.00, 12, 'active', 'toys', 'Bright toy for Prize Tower winners.'),
  ('VIP Party Voucher', 1000, 125.00, 4, 'active', 'events', 'Highest-tier demo item.'),
  ('Retired Token Mug', 120, 9.00, 0, 'inactive', 'merch', 'Inactive item for catalog management demo.');

SET @candy_bar_item := (SELECT gift_shop_item_id FROM gift_shop_items WHERE name = 'Candy Bar');
SET @sticker_pack_item := (SELECT gift_shop_item_id FROM gift_shop_items WHERE name = 'Sticker Pack');
SET @arcade_tumbler_item := (SELECT gift_shop_item_id FROM gift_shop_items WHERE name = 'Arcade Tumbler');
SET @mystery_plush_item := (SELECT gift_shop_item_id FROM gift_shop_items WHERE name = 'Mystery Plush');
SET @led_sword_item := (SELECT gift_shop_item_id FROM gift_shop_items WHERE name = 'LED Sword');

-- Attendee sessions across the operating week.
INSERT INTO attendee_sessions (attendee_id, department_id, employee_id, display_name, admission_mode, entrance_fee_tickets, payout_tickets, notes, opened_at, closed_at)
VALUES
  (NULL, @retro_department, @arcade_employee, 'Walk-In Retro Active', 'walk_in', 25, NULL, 'Active family group.', DATE_SUB(NOW(), INTERVAL 45 MINUTE), NULL),
  (@alice_attendee, @vr_department, @laser_employee, 'Alice VR Run', 'member_wallet', 60, 120, 'Member VR run.', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 6 DAY), INTERVAL -1 HOUR)),
  (NULL, @laser_department, @laser_employee, 'Laser Team Alpha', 'walk_in', 35, 70, 'Team payout after maze run.', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 5 DAY), INTERVAL -90 MINUTE)),
  (@jordan_attendee, @pinball_department, @pinball_employee, 'Jordan Pinball', 'member_wallet', 20, 35, 'Pinball league practice.', DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 4 DAY), INTERVAL -2 HOUR)),
  (NULL, @racing_department, @racing_employee, 'Racing Walk-In', 'walk_in', 30, 45, 'Racing tournament heat.', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 3 DAY), INTERVAL -80 MINUTE)),
  (@morgan_attendee, @tower_department, @tower_employee, 'Morgan Tower', 'member_wallet', 45, 300, 'Big Prize Tower win.', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 2 DAY), INTERVAL -1 HOUR)),
  (NULL, @claw_department, @floater_employee, 'Claw Walk-In', 'walk_in', 15, 40, 'Claw payout depleted area budget.', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 2 DAY), INTERVAL -70 MINUTE)),
  (NULL, @retro_department, @arcade_employee, 'Retro Walk-In Closed', 'walk_in', 25, 20, 'Small retro payout.', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(DATE_SUB(NOW(), INTERVAL 1 DAY), INTERVAL -2 HOUR)),
  (NULL, @vr_department, @laser_employee, 'VR Active Walk-In', 'walk_in', 60, NULL, 'Active VR session.', DATE_SUB(NOW(), INTERVAL 35 MINUTE), NULL),
  (NULL, @pinball_department, @pinball_employee, 'Pinball Active Pair', 'walk_in', 20, NULL, 'Active pinball pair.', DATE_SUB(NOW(), INTERVAL 20 MINUTE), NULL);

SET @session_retro_active := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Walk-In Retro Active');
SET @session_alice_vr := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Alice VR Run');
SET @session_laser_alpha := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Laser Team Alpha');
SET @session_jordan_pinball := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Jordan Pinball');
SET @session_racing := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Racing Walk-In');
SET @session_morgan_tower := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Morgan Tower');
SET @session_claw := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Claw Walk-In');
SET @session_retro_closed := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Retro Walk-In Closed');
SET @session_vr_active := (SELECT session_id FROM attendee_sessions WHERE display_name = 'VR Active Walk-In');
SET @session_pinball_active := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Pinball Active Pair');

-- Ticket accounts.
INSERT INTO ticket_accounts (account_code, account_kind, department_id, attendee_id, attendee_session_id, balance)
VALUES
  ('gift_shop_budget', 'gift_shop_budget', NULL, NULL, NULL, 1405),
  ('gift_shop_revenue', 'gift_shop_revenue', NULL, NULL, NULL, 760),
  ('gift_shop_investment', 'gift_shop_investment', NULL, NULL, NULL, 2200),
  (CONCAT('department_reserve:', @retro_department), 'department_reserve', @retro_department, NULL, NULL, 310),
  (CONCAT('department_generated:', @retro_department), 'department_generated', @retro_department, NULL, NULL, 0),
  (CONCAT('department_reserve:', @vr_department), 'department_reserve', @vr_department, NULL, NULL, 220),
  (CONCAT('department_generated:', @vr_department), 'department_generated', @vr_department, NULL, NULL, 0),
  (CONCAT('department_reserve:', @claw_department), 'department_reserve', @claw_department, NULL, NULL, 0),
  (CONCAT('department_generated:', @claw_department), 'department_generated', @claw_department, NULL, NULL, 0),
  (CONCAT('department_reserve:', @laser_department), 'department_reserve', @laser_department, NULL, NULL, 260),
  (CONCAT('department_generated:', @laser_department), 'department_generated', @laser_department, NULL, NULL, 0),
  (CONCAT('department_reserve:', @pinball_department), 'department_reserve', @pinball_department, NULL, NULL, 185),
  (CONCAT('department_generated:', @pinball_department), 'department_generated', @pinball_department, NULL, NULL, 0),
  (CONCAT('department_reserve:', @racing_department), 'department_reserve', @racing_department, NULL, NULL, 205),
  (CONCAT('department_generated:', @racing_department), 'department_generated', @racing_department, NULL, NULL, 0),
  (CONCAT('department_reserve:', @rhythm_department), 'department_reserve', @rhythm_department, NULL, NULL, 120),
  (CONCAT('department_generated:', @rhythm_department), 'department_generated', @rhythm_department, NULL, NULL, 0),
  (CONCAT('department_reserve:', @tower_department), 'department_reserve', @tower_department, NULL, NULL, 90),
  (CONCAT('department_generated:', @tower_department), 'department_generated', @tower_department, NULL, NULL, 0),
  (CONCAT('department_reserve:', @gift_shop_department), 'department_reserve', @gift_shop_department, NULL, NULL, 500),
  (CONCAT('department_generated:', @gift_shop_department), 'department_generated', @gift_shop_department, NULL, NULL, 0),
  (CONCAT('department_reserve:', @support_department), 'department_reserve', @support_department, NULL, NULL, 80),
  (CONCAT('department_generated:', @support_department), 'department_generated', @support_department, NULL, NULL, 0),
  (CONCAT('member_wallet:', @alice_attendee), 'member_wallet', NULL, @alice_attendee, NULL, 210),
  (CONCAT('member_wallet:', @casey_attendee), 'member_wallet', NULL, @casey_attendee, NULL, 90),
  (CONCAT('member_wallet:', @jordan_attendee), 'member_wallet', NULL, @jordan_attendee, NULL, 115),
  (CONCAT('member_wallet:', @morgan_attendee), 'member_wallet', NULL, @morgan_attendee, NULL, 390),
  (CONCAT('session_wallet:', @session_retro_active), 'session_wallet', NULL, NULL, @session_retro_active, 0),
  (CONCAT('session_wallet:', @session_alice_vr), 'session_wallet', NULL, NULL, @session_alice_vr, 0),
  (CONCAT('session_wallet:', @session_laser_alpha), 'session_wallet', NULL, NULL, @session_laser_alpha, 70),
  (CONCAT('session_wallet:', @session_jordan_pinball), 'session_wallet', NULL, NULL, @session_jordan_pinball, 0),
  (CONCAT('session_wallet:', @session_racing), 'session_wallet', NULL, NULL, @session_racing, 25),
  (CONCAT('session_wallet:', @session_morgan_tower), 'session_wallet', NULL, NULL, @session_morgan_tower, 0),
  (CONCAT('session_wallet:', @session_claw), 'session_wallet', NULL, NULL, @session_claw, 40),
  (CONCAT('session_wallet:', @session_retro_closed), 'session_wallet', NULL, NULL, @session_retro_closed, 0),
  (CONCAT('session_wallet:', @session_vr_active), 'session_wallet', NULL, NULL, @session_vr_active, 0),
  (CONCAT('session_wallet:', @session_pinball_active), 'session_wallet', NULL, NULL, @session_pinball_active, 0);

SET @gift_shop_budget_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = 'gift_shop_budget');
SET @gift_shop_revenue_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = 'gift_shop_revenue');
SET @gift_shop_investment_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = 'gift_shop_investment');
SET @retro_reserve_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('department_reserve:', @retro_department));
SET @vr_reserve_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('department_reserve:', @vr_department));
SET @claw_reserve_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('department_reserve:', @claw_department));
SET @laser_reserve_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('department_reserve:', @laser_department));
SET @pinball_reserve_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('department_reserve:', @pinball_department));
SET @racing_reserve_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('department_reserve:', @racing_department));
SET @tower_reserve_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('department_reserve:', @tower_department));
SET @alice_wallet_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('member_wallet:', @alice_attendee));
SET @casey_wallet_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('member_wallet:', @casey_attendee));
SET @jordan_wallet_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('member_wallet:', @jordan_attendee));
SET @morgan_wallet_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('member_wallet:', @morgan_attendee));
SET @session_laser_wallet := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('session_wallet:', @session_laser_alpha));
SET @session_racing_wallet := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('session_wallet:', @session_racing));
SET @session_claw_wallet := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('session_wallet:', @session_claw));

-- Ticket transactions: one week of owner, department, member, and gift shop activity.
INSERT INTO ticket_transactions (transaction_type, source_account_id, destination_account_id, amount, department_id, attendee_id, attendee_session_id, employee_id, gift_shop_item_id, created_by_user_id, note, created_at)
VALUES
  ('owner_investment', NULL, @gift_shop_budget_account, 2200, NULL, NULL, NULL, NULL, NULL, @owner_user, 'Owner created weekly operating credits.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  ('owner_investment', NULL, @gift_shop_investment_account, 2200, NULL, NULL, NULL, NULL, NULL, @owner_user, 'Owner investment reporting counter.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  ('owner_allocation', @gift_shop_budget_account, @retro_reserve_account, 420, @retro_department, NULL, NULL, NULL, NULL, @owner_user, 'Weekly Retro budget.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  ('owner_allocation', @gift_shop_budget_account, @vr_reserve_account, 340, @vr_department, NULL, NULL, NULL, NULL, @owner_user, 'Weekly VR budget.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  ('owner_allocation', @gift_shop_budget_account, @laser_reserve_account, 330, @laser_department, NULL, NULL, NULL, NULL, @owner_user, 'Weekly Laser Maze budget.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  ('owner_allocation', @gift_shop_budget_account, @pinball_reserve_account, 240, @pinball_department, NULL, NULL, NULL, NULL, @owner_user, 'Weekly Pinball budget.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  ('owner_allocation', @gift_shop_budget_account, @racing_reserve_account, 250, @racing_department, NULL, NULL, NULL, NULL, @owner_user, 'Weekly Racing budget.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  ('owner_allocation', @gift_shop_budget_account, @tower_reserve_account, 390, @tower_department, NULL, NULL, NULL, NULL, @owner_user, 'Weekly Prize Tower budget.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  ('owner_allocation', @gift_shop_budget_account, @claw_reserve_account, 40, @claw_department, NULL, NULL, NULL, NULL, @owner_user, 'Small Claw Corner budget.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  ('manual_override', NULL, @alice_wallet_account, 180, NULL, @alice_attendee, NULL, @support_employee, NULL, @support_user, 'Imported member wallet balance for Alice.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
  ('manual_override', NULL, @jordan_wallet_account, 130, NULL, @jordan_attendee, NULL, @support_employee, NULL, @support_user, 'Imported member wallet balance for Jordan.', DATE_SUB(NOW(), INTERVAL 5 DAY)),
  ('manual_override', NULL, @morgan_wallet_account, 420, NULL, @morgan_attendee, NULL, @support_employee, NULL, @support_user, 'Imported member wallet balance for Morgan.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
  ('department_admission', NULL, @gift_shop_budget_account, 60, @vr_department, @alice_attendee, @session_alice_vr, @laser_employee, NULL, @laser_user, 'Alice VR admission credited to owner credits.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
  ('department_payout', @vr_reserve_account, @alice_wallet_account, 120, @vr_department, @alice_attendee, @session_alice_vr, @laser_employee, NULL, @laser_user, 'Alice VR payout.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
  ('department_admission', NULL, @gift_shop_budget_account, 35, @laser_department, NULL, @session_laser_alpha, @laser_employee, NULL, @laser_user, 'Laser Team Alpha admission.', DATE_SUB(NOW(), INTERVAL 5 DAY)),
  ('department_payout', @laser_reserve_account, @session_laser_wallet, 70, @laser_department, NULL, @session_laser_alpha, @laser_employee, NULL, @laser_user, 'Laser Team Alpha payout.', DATE_SUB(NOW(), INTERVAL 5 DAY)),
  ('department_admission', @jordan_wallet_account, @gift_shop_budget_account, 20, @pinball_department, @jordan_attendee, @session_jordan_pinball, @pinball_employee, NULL, @pinball_user, 'Jordan Pinball member admission.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
  ('department_payout', @pinball_reserve_account, @jordan_wallet_account, 35, @pinball_department, @jordan_attendee, @session_jordan_pinball, @pinball_employee, NULL, @pinball_user, 'Pinball payout.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
  ('department_admission', NULL, @gift_shop_budget_account, 30, @racing_department, NULL, @session_racing, @racing_employee, NULL, @racing_user, 'Racing walk-in admission.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  ('department_payout', @racing_reserve_account, @session_racing_wallet, 45, @racing_department, NULL, @session_racing, @racing_employee, NULL, @racing_user, 'Racing payout.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  ('department_admission', @morgan_wallet_account, @gift_shop_budget_account, 45, @tower_department, @morgan_attendee, @session_morgan_tower, @tower_employee, NULL, @tower_user, 'Morgan Prize Tower admission.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
  ('department_payout', @tower_reserve_account, @morgan_wallet_account, 300, @tower_department, @morgan_attendee, @session_morgan_tower, @tower_employee, NULL, @tower_user, 'Big Prize Tower payout.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
  ('department_admission', NULL, @gift_shop_budget_account, 15, @claw_department, NULL, @session_claw, @floater_employee, NULL, @floater_user, 'Claw walk-in admission.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
  ('department_payout', @claw_reserve_account, @session_claw_wallet, 40, @claw_department, NULL, @session_claw, @floater_employee, NULL, @floater_user, 'Claw payout depleted budget.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
  ('department_admission', NULL, @gift_shop_budget_account, 25, @retro_department, NULL, @session_retro_closed, @arcade_employee, NULL, @arcade_user, 'Retro closed session admission.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
  ('department_payout', @retro_reserve_account, NULL, 20, @retro_department, NULL, @session_retro_closed, @arcade_employee, NULL, @arcade_user, 'Retro closed session payout redeemed immediately.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
  ('department_admission', NULL, @gift_shop_budget_account, 25, @retro_department, NULL, @session_retro_active, @arcade_employee, NULL, @arcade_user, 'Active Retro session admission.', DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
  ('department_admission', NULL, @gift_shop_budget_account, 60, @vr_department, NULL, @session_vr_active, @laser_employee, NULL, @laser_user, 'Active VR session admission.', DATE_SUB(NOW(), INTERVAL 35 MINUTE)),
  ('department_admission', NULL, @gift_shop_budget_account, 20, @pinball_department, NULL, @session_pinball_active, @pinball_employee, NULL, @pinball_user, 'Active Pinball session admission.', DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
  ('gift_shop_redemption', @session_laser_wallet, @gift_shop_revenue_account, 20, @gift_shop_department, NULL, @session_laser_alpha, @gift_shop_employee, @sticker_pack_item, @gift_shop_user, 'Laser team used remaining session tickets.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
  ('gift_shop_redemption', @jordan_wallet_account, @gift_shop_revenue_account, 80, @gift_shop_department, @jordan_attendee, @session_jordan_pinball, @gift_shop_employee, @arcade_tumbler_item, @gift_shop_user, 'Jordan redeemed an Arcade Tumbler.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
  ('gift_shop_redemption', @morgan_wallet_account, @gift_shop_revenue_account, 400, @gift_shop_department, @morgan_attendee, @session_morgan_tower, @gift_shop_employee, @led_sword_item, @gift_shop_user, 'Morgan redeemed an LED Sword.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
  ('gift_shop_redemption', @session_claw_wallet, @gift_shop_revenue_account, 20, @gift_shop_department, NULL, @session_claw, @gift_shop_employee, @sticker_pack_item, @gift_shop_user, 'Claw walk-in redeemed stickers.', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Gift shop redemption rows.
INSERT INTO gift_shop_redemptions (gift_shop_item_id, department_id, employee_id, attendee_id, attendee_session_id, source_account_id, quantity, total_tickets, notes, redeemed_at)
VALUES
  (@sticker_pack_item, @gift_shop_department, @gift_shop_employee, NULL, @session_laser_alpha, @session_laser_wallet, 1, 20, 'Laser team redeemed sticker pack.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
  (@arcade_tumbler_item, @gift_shop_department, @gift_shop_employee, @jordan_attendee, @session_jordan_pinball, @jordan_wallet_account, 1, 80, 'Jordan redeemed an Arcade Tumbler.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
  (@led_sword_item, @gift_shop_department, @gift_shop_employee, @morgan_attendee, @session_morgan_tower, @morgan_wallet_account, 1, 400, 'Morgan redeemed an LED Sword.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
  (@sticker_pack_item, @gift_shop_department, @gift_shop_employee, NULL, @session_claw, @session_claw_wallet, 1, 20, 'Claw walk-in redeemed stickers.', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Snapshot report.
INSERT INTO revenue_reports (total_ticket_admissions, total_ticket_payouts, total_gift_shop_redemptions, total_owner_investment, active_attendees, start_date, end_date, generated_by)
VALUES
  (395, 630, 520, 2200, 3, DATE_SUB(CURDATE(), INTERVAL 7 DAY), CURDATE(), @owner_user);
