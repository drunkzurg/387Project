-- 000_run_all.sql
-- Full schema bootstrap for the ticket-based arcade management system.
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
  department_type ENUM('play_area','gift_shop','customer_support') NOT NULL DEFAULT 'play_area',
  entrance_fee_tickets INT UNSIGNED NOT NULL DEFAULT 10,
  capacity INT UNSIGNED NOT NULL DEFAULT 0,
  operating_status ENUM('active','out_of_order','inactive') NOT NULL DEFAULT 'active',
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
  end_time DATETIME NULL,
  entry_type ENUM('live','manual') NOT NULL DEFAULT 'manual',
  PRIMARY KEY (shift_id),
  KEY idx_shifts_employee (employee_id),
  KEY idx_shifts_start (start_time),
  CONSTRAINT fk_shifts_employee
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 004b_create_employee_sick_requests.sql
CREATE TABLE IF NOT EXISTS employee_sick_requests (
  sick_request_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT UNSIGNED NOT NULL,
  request_date DATE NOT NULL,
  status ENUM('waiting','approved','denied') NOT NULL DEFAULT 'waiting',
  notes VARCHAR(255) NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by_user_id INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_notes VARCHAR(255) NULL,
  PRIMARY KEY (sick_request_id),
  UNIQUE KEY uq_sick_request_employee_day (employee_id, request_date),
  KEY idx_sick_requests_employee (employee_id),
  KEY idx_sick_requests_status (status),
  CONSTRAINT fk_sick_requests_employee
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sick_requests_reviewer
    FOREIGN KEY (reviewed_by_user_id) REFERENCES users(user_id)
    ON DELETE SET NULL ON UPDATE CASCADE
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

-- 006_create_employee_transfers.sql
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

-- 006b_create_hr_action_logs.sql
CREATE TABLE IF NOT EXISTS hr_action_logs (
  hr_action_log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  action_type VARCHAR(60) NOT NULL,
  employee_id INT UNSIGNED NULL,
  handled_by_user_id INT UNSIGNED NOT NULL,
  details VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (hr_action_log_id),
  KEY idx_hr_action_logs_employee (employee_id),
  KEY idx_hr_action_logs_user (handled_by_user_id),
  KEY idx_hr_action_logs_created (created_at),
  CONSTRAINT fk_hr_action_logs_employee
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_hr_action_logs_user
    FOREIGN KEY (handled_by_user_id) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 007_create_attendees.sql
CREATE TABLE IF NOT EXISTS attendees (
  attendee_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) NULL,
  membership_code VARCHAR(50) NULL,
  phone VARCHAR(40) NULL,
  is_member TINYINT(1) NOT NULL DEFAULT 0,
  verified_by_employee_id INT UNSIGNED NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (attendee_id),
  UNIQUE KEY uq_attendees_email (email),
  UNIQUE KEY uq_attendees_membership_code (membership_code),
  KEY idx_attendees_verified_by (verified_by_employee_id),
  CONSTRAINT fk_attendees_verified_by
    FOREIGN KEY (verified_by_employee_id) REFERENCES employees(employee_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 008_create_attendee_sessions.sql
CREATE TABLE IF NOT EXISTS attendee_sessions (
  session_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  attendee_id INT UNSIGNED NULL,
  department_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  display_name VARCHAR(150) NOT NULL,
  admission_mode ENUM('walk_in','member_wallet','manual_override') NOT NULL DEFAULT 'walk_in',
  entrance_fee_tickets INT UNSIGNED NOT NULL,
  payout_tickets INT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  PRIMARY KEY (session_id),
  KEY idx_sessions_attendee (attendee_id),
  KEY idx_sessions_department (department_id),
  KEY idx_sessions_employee (employee_id),
  KEY idx_sessions_closed (closed_at),
  CONSTRAINT fk_sessions_attendee
    FOREIGN KEY (attendee_id) REFERENCES attendees(attendee_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_sessions_department
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_sessions_employee
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 009_create_gift_shop_items.sql
CREATE TABLE IF NOT EXISTS gift_shop_items (
  gift_shop_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  ticket_price INT UNSIGNED NOT NULL,
  cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  category VARCHAR(100) NULL,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (gift_shop_item_id),
  UNIQUE KEY uq_gift_shop_items_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 010_create_ticket_accounts.sql
CREATE TABLE IF NOT EXISTS ticket_accounts (
  ticket_account_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_code VARCHAR(100) NOT NULL,
  account_kind ENUM(
    'gift_shop_budget',
    'gift_shop_revenue',
    'gift_shop_investment',
    'department_reserve',
    'department_generated',
    'member_wallet',
    'session_wallet'
  ) NOT NULL,
  department_id INT UNSIGNED NULL,
  attendee_id INT UNSIGNED NULL,
  attendee_session_id INT UNSIGNED NULL,
  balance BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_account_id),
  UNIQUE KEY uq_ticket_accounts_code (account_code),
  UNIQUE KEY uq_ticket_accounts_department_kind (account_kind, department_id),
  UNIQUE KEY uq_ticket_accounts_attendee_kind (account_kind, attendee_id),
  UNIQUE KEY uq_ticket_accounts_session_kind (account_kind, attendee_session_id),
  KEY idx_ticket_accounts_kind (account_kind),
  CONSTRAINT fk_ticket_accounts_department
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ticket_accounts_attendee
    FOREIGN KEY (attendee_id) REFERENCES attendees(attendee_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ticket_accounts_session
    FOREIGN KEY (attendee_session_id) REFERENCES attendee_sessions(session_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 011_create_ticket_transactions.sql
CREATE TABLE IF NOT EXISTS ticket_transactions (
  ticket_transaction_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  transaction_type ENUM(
    'department_admission',
    'department_payout',
    'gift_shop_redemption',
    'owner_allocation',
    'owner_generated_transfer',
    'owner_investment',
    'member_claim_transfer',
    'manual_override'
  ) NOT NULL,
  source_account_id INT UNSIGNED NULL,
  destination_account_id INT UNSIGNED NULL,
  amount BIGINT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NULL,
  attendee_id INT UNSIGNED NULL,
  attendee_session_id INT UNSIGNED NULL,
  employee_id INT UNSIGNED NULL,
  gift_shop_item_id INT UNSIGNED NULL,
  created_by_user_id INT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_transaction_id),
  KEY idx_ticket_transactions_type (transaction_type),
  KEY idx_ticket_transactions_department (department_id),
  KEY idx_ticket_transactions_attendee (attendee_id),
  KEY idx_ticket_transactions_session (attendee_session_id),
  KEY idx_ticket_transactions_employee (employee_id),
  KEY idx_ticket_transactions_user (created_by_user_id),
  CONSTRAINT fk_ticket_transactions_source
    FOREIGN KEY (source_account_id) REFERENCES ticket_accounts(ticket_account_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ticket_transactions_destination
    FOREIGN KEY (destination_account_id) REFERENCES ticket_accounts(ticket_account_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ticket_transactions_department
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ticket_transactions_attendee
    FOREIGN KEY (attendee_id) REFERENCES attendees(attendee_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ticket_transactions_session
    FOREIGN KEY (attendee_session_id) REFERENCES attendee_sessions(session_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ticket_transactions_employee
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ticket_transactions_item
    FOREIGN KEY (gift_shop_item_id) REFERENCES gift_shop_items(gift_shop_item_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ticket_transactions_user
    FOREIGN KEY (created_by_user_id) REFERENCES users(user_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 012_create_gift_shop_redemptions.sql
CREATE TABLE IF NOT EXISTS gift_shop_redemptions (
  redemption_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  gift_shop_item_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  attendee_id INT UNSIGNED NULL,
  attendee_session_id INT UNSIGNED NULL,
  source_account_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  total_tickets BIGINT UNSIGNED NOT NULL,
  notes VARCHAR(255) NULL,
  redeemed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (redemption_id),
  KEY idx_gift_shop_redemptions_item (gift_shop_item_id),
  KEY idx_gift_shop_redemptions_department (department_id),
  KEY idx_gift_shop_redemptions_employee (employee_id),
  KEY idx_gift_shop_redemptions_attendee (attendee_id),
  KEY idx_gift_shop_redemptions_session (attendee_session_id),
  CONSTRAINT fk_gift_shop_redemptions_item
    FOREIGN KEY (gift_shop_item_id) REFERENCES gift_shop_items(gift_shop_item_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_gift_shop_redemptions_department
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_gift_shop_redemptions_employee
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_gift_shop_redemptions_attendee
    FOREIGN KEY (attendee_id) REFERENCES attendees(attendee_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_gift_shop_redemptions_session
    FOREIGN KEY (attendee_session_id) REFERENCES attendee_sessions(session_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_gift_shop_redemptions_source
    FOREIGN KEY (source_account_id) REFERENCES ticket_accounts(ticket_account_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 013_create_revenue_reports.sql
CREATE TABLE IF NOT EXISTS revenue_reports (
  report_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  total_ticket_admissions BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_ticket_payouts BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_gift_shop_redemptions BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_owner_investment BIGINT UNSIGNED NOT NULL DEFAULT 0,
  active_attendees INT UNSIGNED NOT NULL DEFAULT 0,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  generated_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (report_id),
  KEY idx_reports_dates (start_date, end_date),
  KEY idx_reports_generated_by (generated_by),
  CONSTRAINT fk_reports_generated_by
    FOREIGN KEY (generated_by) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
