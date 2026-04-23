-- 001_seed_sample.sql
-- Demo-ready data for the ticket-based arcade management system.
-- Assumes sql/migrations/000_run_all.sql has already been applied.

-- Common password for all seed users is: password
-- bcrypt hash generated via PHP password_hash('password', PASSWORD_DEFAULT)
-- helper: php scripts/hash_password.php password
SET @pw := '$2y$12$3xyBZgPV9pqcI.h0LTf9X.2BmJd1M47oLNNFGRDz5abHXLxLV6.Xm';

-- Users
INSERT INTO users (name, email, password_hash, role, pending_approval)
VALUES
  ('System Admin', 'admin@arcade.local', @pw, 'sys_admin', 0),
  ('Owner One', 'owner@arcade.local', @pw, 'owner', 0),
  ('HR One', 'hr@arcade.local', @pw, 'hr', 0),
  ('Arcade Host', 'employee@arcade.local', @pw, 'employee', 0),
  ('Gift Shop Clerk', 'giftshop@arcade.local', @pw, 'employee', 0),
  ('Support Agent', 'support@arcade.local', @pw, 'employee', 0);

-- Departments
INSERT INTO departments (name, department_type, entrance_fee_tickets, operating_status, description)
VALUES
  ('Retro Arcade', 'play_area', 25, 'active', 'Classic redemption games and family play.'),
  ('VR Arena', 'play_area', 60, 'active', 'Premium high-ticket experience.'),
  ('Claw Corner', 'play_area', 15, 'out_of_order', 'Small claw-machine area used for the out-of-order demo.'),
  ('Gift Shop', 'gift_shop', 0, 'active', 'Prize counter and gift redemption desk.'),
  ('Customer Support', 'customer_support', 0, 'active', 'Membership verification and claim review.');

SET @retro_department := (SELECT department_id FROM departments WHERE name = 'Retro Arcade' LIMIT 1);
SET @vr_department := (SELECT department_id FROM departments WHERE name = 'VR Arena' LIMIT 1);
SET @claw_department := (SELECT department_id FROM departments WHERE name = 'Claw Corner' LIMIT 1);
SET @gift_shop_department := (SELECT department_id FROM departments WHERE name = 'Gift Shop' LIMIT 1);
SET @support_department := (SELECT department_id FROM departments WHERE name = 'Customer Support' LIMIT 1);

-- Employees
INSERT INTO employees (user_id, name, department_id, hourly_wage, status)
VALUES
  ((SELECT user_id FROM users WHERE email = 'owner@arcade.local' LIMIT 1), 'Owner One', @gift_shop_department, 25.00, 'active'),
  ((SELECT user_id FROM users WHERE email = 'hr@arcade.local' LIMIT 1), 'HR One', @support_department, 22.00, 'active'),
  ((SELECT user_id FROM users WHERE email = 'employee@arcade.local' LIMIT 1), 'Arcade Host', @retro_department, 16.50, 'active'),
  ((SELECT user_id FROM users WHERE email = 'giftshop@arcade.local' LIMIT 1), 'Gift Shop Clerk', @gift_shop_department, 16.00, 'active'),
  ((SELECT user_id FROM users WHERE email = 'support@arcade.local' LIMIT 1), 'Support Agent', @support_department, 17.00, 'active');

SET @owner_user := (SELECT user_id FROM users WHERE email = 'owner@arcade.local' LIMIT 1);
SET @hr_user := (SELECT user_id FROM users WHERE email = 'hr@arcade.local' LIMIT 1);
SET @arcade_user := (SELECT user_id FROM users WHERE email = 'employee@arcade.local' LIMIT 1);
SET @gift_shop_user := (SELECT user_id FROM users WHERE email = 'giftshop@arcade.local' LIMIT 1);
SET @support_user := (SELECT user_id FROM users WHERE email = 'support@arcade.local' LIMIT 1);

SET @owner_employee := (SELECT employee_id FROM employees WHERE user_id = @owner_user LIMIT 1);
SET @arcade_employee := (SELECT employee_id FROM employees WHERE user_id = @arcade_user LIMIT 1);
SET @gift_shop_employee := (SELECT employee_id FROM employees WHERE user_id = @gift_shop_user LIMIT 1);
SET @support_employee := (SELECT employee_id FROM employees WHERE user_id = @support_user LIMIT 1);

-- Attendees / members
INSERT INTO attendees (name, email, membership_code, is_member, verified_by_employee_id, verified_at)
VALUES
  ('Alice Member', 'alice.member@example.com', 'MEM-1001', 1, @support_employee, DATE_SUB(NOW(), INTERVAL 4 DAY)),
  ('Casey Claim', 'casey.claim@example.com', 'MEM-1002', 1, @support_employee, DATE_SUB(NOW(), INTERVAL 1 DAY));

SET @alice_attendee := (SELECT attendee_id FROM attendees WHERE membership_code = 'MEM-1001' LIMIT 1);
SET @casey_attendee := (SELECT attendee_id FROM attendees WHERE membership_code = 'MEM-1002' LIMIT 1);

-- Attendee sessions
INSERT INTO attendee_sessions (
  attendee_id,
  department_id,
  employee_id,
  display_name,
  admission_mode,
  entrance_fee_tickets,
  payout_tickets,
  notes,
  opened_at,
  closed_at
)
VALUES
  (NULL, @retro_department, @arcade_employee, 'Walk-In 101', 'walk_in', 25, NULL, 'Family of three currently playing.', DATE_SUB(NOW(), INTERVAL 45 MINUTE), NULL),
  (NULL, @retro_department, @arcade_employee, 'Walk-In 102', 'walk_in', 25, 40, 'Closed with a small payout and partial gift redemption.', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 95 MINUTE)),
  (@alice_attendee, @vr_department, @arcade_employee, 'Alice Member', 'member_wallet', 60, 120, 'Member used wallet on admission and later checked out at the gift shop.', DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 5 HOUR)),
  (NULL, @claw_department, @arcade_employee, 'Walk-In 103', 'walk_in', 15, 40, 'Reserve reached zero after this payout.', DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 150 MINUTE)),
  (@casey_attendee, @retro_department, @arcade_employee, 'Walk-In 104', 'walk_in', 25, 90, 'Later verified by customer support and converted into a member wallet.', DATE_SUB(NOW(), INTERVAL 26 HOUR), DATE_SUB(NOW(), INTERVAL 25 HOUR));

SET @session_one := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Walk-In 101' LIMIT 1);
SET @session_two := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Walk-In 102' LIMIT 1);
SET @session_three := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Alice Member' LIMIT 1);
SET @session_four := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Walk-In 103' LIMIT 1);
SET @session_five := (SELECT session_id FROM attendee_sessions WHERE display_name = 'Walk-In 104' LIMIT 1);

-- Gift shop items
INSERT INTO gift_shop_items (name, ticket_price, cost_price, stock, status, category, description)
VALUES
  ('Candy Bar', 10, 0.75, 250, 'active', 'snacks', 'Low-cost snack for quick redemptions.'),
  ('Sticker Pack', 20, 1.50, 120, 'active', 'accessories', 'Popular low-ticket item for walk-ins.'),
  ('Arcade Tumbler', 80, 8.00, 25, 'active', 'merch', 'Reusable tumbler used in the seed redemption demo.'),
  ('Mystery Plush', 250, 11.50, 18, 'active', 'plush', 'Medium-tier redemption item.'),
  ('VIP Party Voucher', 1000, 125.00, 4, 'active', 'events', 'Highest-tier demo item.');

SET @candy_bar_item := (SELECT gift_shop_item_id FROM gift_shop_items WHERE name = 'Candy Bar' LIMIT 1);
SET @sticker_pack_item := (SELECT gift_shop_item_id FROM gift_shop_items WHERE name = 'Sticker Pack' LIMIT 1);
SET @arcade_tumbler_item := (SELECT gift_shop_item_id FROM gift_shop_items WHERE name = 'Arcade Tumbler' LIMIT 1);

-- Ticket accounts
INSERT INTO ticket_accounts (account_code, account_kind, department_id, attendee_id, attendee_session_id, balance)
VALUES
  ('gift_shop_budget', 'gift_shop_budget', NULL, NULL, NULL, 425),
  ('gift_shop_revenue', 'gift_shop_revenue', NULL, NULL, NULL, 100),
  ('gift_shop_investment', 'gift_shop_investment', NULL, NULL, NULL, 700);

INSERT INTO ticket_accounts (account_code, account_kind, department_id, attendee_id, attendee_session_id, balance)
VALUES
  (CONCAT('department_reserve:', @retro_department), 'department_reserve', @retro_department, NULL, NULL, 50),
  (CONCAT('department_generated:', @retro_department), 'department_generated', @retro_department, NULL, NULL, 25),
  (CONCAT('department_reserve:', @vr_department), 'department_reserve', @vr_department, NULL, NULL, 20),
  (CONCAT('department_generated:', @vr_department), 'department_generated', @vr_department, NULL, NULL, 40),
  (CONCAT('department_reserve:', @claw_department), 'department_reserve', @claw_department, NULL, NULL, 0),
  (CONCAT('department_generated:', @claw_department), 'department_generated', @claw_department, NULL, NULL, 0),
  (CONCAT('department_reserve:', @gift_shop_department), 'department_reserve', @gift_shop_department, NULL, NULL, 0),
  (CONCAT('department_generated:', @gift_shop_department), 'department_generated', @gift_shop_department, NULL, NULL, 0),
  (CONCAT('department_reserve:', @support_department), 'department_reserve', @support_department, NULL, NULL, 0),
  (CONCAT('department_generated:', @support_department), 'department_generated', @support_department, NULL, NULL, 0),
  (CONCAT('member_wallet:', @alice_attendee), 'member_wallet', NULL, @alice_attendee, NULL, 180),
  (CONCAT('member_wallet:', @casey_attendee), 'member_wallet', NULL, @casey_attendee, NULL, 90),
  (CONCAT('session_wallet:', @session_one), 'session_wallet', NULL, NULL, @session_one, 0),
  (CONCAT('session_wallet:', @session_two), 'session_wallet', NULL, NULL, @session_two, 20),
  (CONCAT('session_wallet:', @session_three), 'session_wallet', NULL, NULL, @session_three, 0),
  (CONCAT('session_wallet:', @session_four), 'session_wallet', NULL, NULL, @session_four, 40),
  (CONCAT('session_wallet:', @session_five), 'session_wallet', NULL, NULL, @session_five, 0);

SET @gift_shop_budget_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = 'gift_shop_budget' LIMIT 1);
SET @gift_shop_revenue_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = 'gift_shop_revenue' LIMIT 1);
SET @gift_shop_investment_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = 'gift_shop_investment' LIMIT 1);
SET @retro_reserve_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('department_reserve:', @retro_department) LIMIT 1);
SET @retro_generated_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('department_generated:', @retro_department) LIMIT 1);
SET @vr_reserve_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('department_reserve:', @vr_department) LIMIT 1);
SET @vr_generated_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('department_generated:', @vr_department) LIMIT 1);
SET @claw_reserve_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('department_reserve:', @claw_department) LIMIT 1);
SET @claw_generated_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('department_generated:', @claw_department) LIMIT 1);
SET @alice_wallet_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('member_wallet:', @alice_attendee) LIMIT 1);
SET @casey_wallet_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('member_wallet:', @casey_attendee) LIMIT 1);
SET @session_two_wallet_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('session_wallet:', @session_two) LIMIT 1);
SET @session_four_wallet_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('session_wallet:', @session_four) LIMIT 1);
SET @session_five_wallet_account := (SELECT ticket_account_id FROM ticket_accounts WHERE account_code = CONCAT('session_wallet:', @session_five) LIMIT 1);

-- Ticket transactions
INSERT INTO ticket_transactions (
  transaction_type,
  source_account_id,
  destination_account_id,
  amount,
  department_id,
  attendee_id,
  attendee_session_id,
  employee_id,
  gift_shop_item_id,
  created_by_user_id,
  note,
  created_at
)
VALUES
  ('owner_investment', NULL, @gift_shop_budget_account, 700, NULL, NULL, NULL, NULL, NULL, @owner_user, 'Owner created the initial gift shop pool for demo use.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  ('owner_investment', NULL, @gift_shop_investment_account, 700, NULL, NULL, NULL, NULL, NULL, @owner_user, 'Owner investment counter.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  ('owner_allocation', @gift_shop_budget_account, @retro_reserve_account, 180, @retro_department, NULL, NULL, NULL, NULL, @owner_user, 'Initial allocation to Retro Arcade.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  ('owner_allocation', @gift_shop_budget_account, @vr_reserve_account, 140, @vr_department, NULL, NULL, NULL, NULL, @owner_user, 'Initial allocation to VR Arena.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  ('owner_allocation', @gift_shop_budget_account, @claw_reserve_account, 40, @claw_department, NULL, NULL, NULL, NULL, @owner_user, 'Initial allocation to Claw Corner.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  ('manual_override', NULL, @alice_wallet_account, 200, NULL, @alice_attendee, NULL, @support_employee, NULL, @support_user, 'Imported member wallet balance for Alice.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
  ('department_admission', NULL, @retro_generated_account, 25, @retro_department, NULL, @session_one, @arcade_employee, NULL, @arcade_user, 'Walk-in admission for active session.', DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
  ('department_admission', NULL, @retro_generated_account, 25, @retro_department, NULL, @session_two, @arcade_employee, NULL, @arcade_user, 'Walk-in admission.', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
  ('department_admission', @alice_wallet_account, @vr_generated_account, 60, @vr_department, @alice_attendee, @session_three, @arcade_employee, NULL, @arcade_user, 'Member wallet admission.', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
  ('department_admission', NULL, @claw_generated_account, 15, @claw_department, NULL, @session_four, @arcade_employee, NULL, @arcade_user, 'Walk-in admission before reserve depletion.', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
  ('department_admission', NULL, @retro_generated_account, 25, @retro_department, @casey_attendee, @session_five, @arcade_employee, NULL, @arcade_user, 'Walk-in admission later claimed by member.', DATE_SUB(NOW(), INTERVAL 26 HOUR)),
  ('department_payout', @retro_reserve_account, @session_two_wallet_account, 40, @retro_department, NULL, @session_two, @arcade_employee, NULL, @arcade_user, 'Small prize payout for walk-in.', DATE_SUB(NOW(), INTERVAL 95 MINUTE)),
  ('department_payout', @vr_reserve_account, @alice_wallet_account, 120, @vr_department, @alice_attendee, @session_three, @arcade_employee, NULL, @arcade_user, 'Member payout sent directly to wallet.', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
  ('department_payout', @claw_reserve_account, @session_four_wallet_account, 40, @claw_department, NULL, @session_four, @arcade_employee, NULL, @arcade_user, 'Final payout before department went out of order.', DATE_SUB(NOW(), INTERVAL 150 MINUTE)),
  ('department_payout', @retro_reserve_account, @session_five_wallet_account, 90, @retro_department, @casey_attendee, @session_five, @arcade_employee, NULL, @arcade_user, 'High-value payout later moved into a member wallet.', DATE_SUB(NOW(), INTERVAL 25 HOUR)),
  ('member_claim_transfer', @session_five_wallet_account, @casey_wallet_account, 90, @retro_department, @casey_attendee, @session_five, @support_employee, NULL, @support_user, 'Customer-support verification created Casey''s member wallet.', DATE_SUB(NOW(), INTERVAL 24 HOUR)),
  ('owner_generated_transfer', @retro_generated_account, @gift_shop_budget_account, 50, @retro_department, NULL, NULL, NULL, NULL, @owner_user, 'Owner recycled generated tickets from Retro Arcade into the gift shop pool.', DATE_SUB(NOW(), INTERVAL 20 HOUR)),
  ('owner_generated_transfer', @vr_generated_account, @gift_shop_budget_account, 20, @vr_department, NULL, NULL, NULL, NULL, @owner_user, 'Generated transfer from VR Arena.', DATE_SUB(NOW(), INTERVAL 19 HOUR)),
  ('owner_generated_transfer', @claw_generated_account, @gift_shop_budget_account, 15, @claw_department, NULL, NULL, NULL, NULL, @owner_user, 'Generated transfer from Claw Corner.', DATE_SUB(NOW(), INTERVAL 18 HOUR)),
  ('gift_shop_redemption', @session_two_wallet_account, @gift_shop_revenue_account, 20, @gift_shop_department, NULL, @session_two, @gift_shop_employee, @sticker_pack_item, @gift_shop_user, 'Walk-in used remaining tickets on a sticker pack.', DATE_SUB(NOW(), INTERVAL 80 MINUTE)),
  ('gift_shop_redemption', @alice_wallet_account, @gift_shop_revenue_account, 80, @gift_shop_department, @alice_attendee, @session_three, @gift_shop_employee, @arcade_tumbler_item, @gift_shop_user, 'Alice used her member wallet in the gift shop.', DATE_SUB(NOW(), INTERVAL 4 HOUR));

-- Gift shop redemption rows
INSERT INTO gift_shop_redemptions (
  gift_shop_item_id,
  department_id,
  employee_id,
  attendee_id,
  attendee_session_id,
  source_account_id,
  quantity,
  total_tickets,
  notes,
  redeemed_at
)
VALUES
  (@sticker_pack_item, @gift_shop_department, @gift_shop_employee, NULL, @session_two, @session_two_wallet_account, 1, 20, 'Sticker pack redeemed from a walk-in session wallet.', DATE_SUB(NOW(), INTERVAL 80 MINUTE)),
  (@arcade_tumbler_item, @gift_shop_department, @gift_shop_employee, @alice_attendee, @session_three, @alice_wallet_account, 1, 80, 'Alice redeemed an Arcade Tumbler from her member wallet.', DATE_SUB(NOW(), INTERVAL 4 HOUR));

-- Snapshot report
INSERT INTO revenue_reports (
  total_ticket_admissions,
  total_ticket_payouts,
  total_gift_shop_redemptions,
  total_owner_investment,
  active_attendees,
  start_date,
  end_date,
  generated_by
)
VALUES
  (150, 290, 100, 700, 1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), CURDATE(), @owner_user);
