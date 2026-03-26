-- 000_run_all.sql
-- Helper to run all migrations in order.
-- Usage (in MariaDB client):
--   USE bpaudel2;
--   SOURCE /home/bpaudel2/public_html/387Project/sql/migrations/000_run_all.sql;

-- 001_create_users.sql
CREATE TABLE IF NOT EXISTS users (
  user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('sys_admin','owner','employee','hr') NOT NULL,
  pending_approval TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 002_create_departments.sql
CREATE TABLE IF NOT EXISTS departments (
  department_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (department_id),
  UNIQUE KEY uq_departments_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 003_create_employees.sql
CREATE TABLE IF NOT EXISTS employees (
  employee_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  department_id INT UNSIGNED NULL,
  hourly_wage DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('active','transferred','terminated') NOT NULL DEFAULT 'active',
  PRIMARY KEY (employee_id),
  UNIQUE KEY uq_employees_user (user_id),
  KEY idx_employees_department (department_id),
  CONSTRAINT fk_employees_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_employees_department
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 004_create_employee_shifts.sql
CREATE TABLE IF NOT EXISTS employee_shifts (
  shift_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT UNSIGNED NOT NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME NOT NULL,
  PRIMARY KEY (shift_id),
  KEY idx_shifts_employee (employee_id),
  KEY idx_shifts_start (start_time),
  CONSTRAINT fk_shifts_employee
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 005_create_employee_hours.sql
CREATE TABLE IF NOT EXISTS employee_hours (
  log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  hours_worked DECIMAL(5,2) NOT NULL,
  PRIMARY KEY (log_id),
  UNIQUE KEY uq_employee_hours_day (employee_id, `date`),
  CONSTRAINT fk_hours_employee
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 006_create_arcades.sql
CREATE TABLE IF NOT EXISTS arcades (
  arcade_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  cost_per_play DECIMAL(10,2) NOT NULL,
  ticket_min INT UNSIGNED NOT NULL,
  ticket_max INT UNSIGNED NOT NULL,
  num_players INT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  PRIMARY KEY (arcade_id),
  UNIQUE KEY uq_arcades_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 007_create_arcade_usage_logs.sql
CREATE TABLE IF NOT EXISTS arcade_usage_logs (
  usage_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  arcade_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  plays_count INT UNSIGNED NOT NULL DEFAULT 0,
  revenue_generated DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tickets_dispensed INT UNSIGNED NOT NULL DEFAULT 0,
  `date` DATE NOT NULL,
  PRIMARY KEY (usage_id),
  KEY idx_usage_arcade (arcade_id),
  KEY idx_usage_user (user_id),
  KEY idx_usage_date (`date`),
  CONSTRAINT fk_usage_arcade
    FOREIGN KEY (arcade_id) REFERENCES arcades(arcade_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_usage_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 008_create_prizes.sql
CREATE TABLE IF NOT EXISTS prizes (
  prize_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  ticket_cost INT UNSIGNED NOT NULL,
  cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  stock INT NOT NULL DEFAULT 0,
  category VARCHAR(100) NULL,
  PRIMARY KEY (prize_id),
  UNIQUE KEY uq_prizes_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 009_create_prize_redemptions.sql
CREATE TABLE IF NOT EXISTS prize_redemptions (
  redemption_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prize_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NULL,
  tickets_used INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  `date` DATE NOT NULL,
  PRIMARY KEY (redemption_id),
  KEY idx_redemptions_prize (prize_id),
  KEY idx_redemptions_employee (employee_id),
  KEY idx_redemptions_date (`date`),
  CONSTRAINT fk_redemptions_prize
    FOREIGN KEY (prize_id) REFERENCES prizes(prize_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_redemptions_employee
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 010_create_employee_transfers.sql
CREATE TABLE IF NOT EXISTS employee_transfers (
  transfer_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT UNSIGNED NOT NULL,
  from_department INT UNSIGNED NULL,
  to_department INT UNSIGNED NULL,
  `date` DATE NOT NULL,
  handled_by_user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (transfer_id),
  KEY idx_transfers_employee (employee_id),
  KEY idx_transfers_from (from_department),
  KEY idx_transfers_to (to_department),
  KEY idx_transfers_handler (handled_by_user_id),
  CONSTRAINT fk_transfers_employee
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_transfers_from_department
    FOREIGN KEY (from_department) REFERENCES departments(department_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_transfers_to_department
    FOREIGN KEY (to_department) REFERENCES departments(department_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_transfers_handler
    FOREIGN KEY (handled_by_user_id) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 011_create_revenue_reports.sql
CREATE TABLE IF NOT EXISTS revenue_reports (
  report_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  total_arcade_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_prize_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_wages_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  net_profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  generated_by INT UNSIGNED NOT NULL,
  PRIMARY KEY (report_id),
  KEY idx_reports_dates (start_date, end_date),
  KEY idx_reports_generated_by (generated_by),
  CONSTRAINT fk_reports_generated_by
    FOREIGN KEY (generated_by) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
