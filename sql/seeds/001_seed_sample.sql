-- 001_seed_sample.sql
-- Inserts sample data for development/testing.
-- Assumes schema from sql/migrations/*.sql is already applied.

-- Common password for seed users is: password
-- bcrypt hash generated via PHP password_hash('password', PASSWORD_DEFAULT)
-- helper: php scripts/hash_password.php password
SET @pw := '$2y$12$3xyBZgPV9pqcI.h0LTf9X.2BmJd1M47oLNNFGRDz5abHXLxLV6.Xm';

-- Users (one per role)
INSERT INTO users (name, email, password_hash, role, pending_approval)
VALUES
  ('System Admin', 'admin@arcade.local', @pw, 'sys_admin', 0),
  ('Owner One', 'owner@arcade.local', @pw, 'owner', 0),
  ('HR One', 'hr@arcade.local', @pw, 'hr', 0),
  ('Employee One', 'employee@arcade.local', @pw, 'employee', 0);

-- Departments
INSERT INTO departments (name) VALUES
  ('arcade'),
  ('giftshop');

-- Employees (link users -> departments)
INSERT INTO employees (user_id, name, department_id, hourly_wage, status)
SELECT u.user_id, u.name,
       (SELECT d.department_id FROM departments d WHERE d.name = 'arcade' LIMIT 1),
       15.00,
       'active'
FROM users u
WHERE u.role IN ('employee','owner','hr');

-- Arcades
INSERT INTO arcades (name, cost_per_play, ticket_min, ticket_max, num_players, status) VALUES
  ('Skee Ball', 1.00, 1, 10, 1, 'active'),
  ('Air Hockey', 2.00, 2, 20, 2, 'active'),
  ('Racing Duo', 2.50, 3, 25, 2, 'maintenance');

-- Prizes
INSERT INTO prizes (name, ticket_cost, cost_price, stock, category) VALUES
  ('Candy Bar', 10, 0.75, 200, 'candy'),
  ('Plush Toy', 250, 8.50, 25, 'plush'),
  ('RC Car', 800, 35.00, 5, 'toys');

-- Arcade usage logs (supervised by employee user)
INSERT INTO arcade_usage_logs (arcade_id, user_id, plays_count, revenue_generated, tickets_dispensed, `date`)
SELECT a.arcade_id,
       (SELECT u.user_id FROM users u WHERE u.role = 'employee' LIMIT 1),
       120,
       120.00,
       900,
       CURDATE()
FROM arcades a
WHERE a.name = 'Skee Ball'
LIMIT 1;

-- Prize redemptions (by employee)
INSERT INTO prize_redemptions (prize_id, employee_id, tickets_used, quantity, `date`)
SELECT p.prize_id,
       (SELECT e.employee_id FROM employees e
         JOIN users u ON u.user_id = e.user_id
         WHERE u.role = 'employee' LIMIT 1),
       20,
       2,
       CURDATE()
FROM prizes p
WHERE p.name = 'Candy Bar'
LIMIT 1;
