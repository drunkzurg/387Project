# Arcade Management System — User and Installation Manual

## Purpose and audience

This manual explains **what** the Arcade Management System does in business terms, **who** uses each part of the application and **why** responsibilities are separated, and **how** to install and run the software from a fresh copy of the project (for example an unpacked zip on a new computer with an empty database).

After reading this document you should be able to:

- Plan **user stories** and map them to roles and database concepts.
- Perform a **clean installation** (PHP, MariaDB/MySQL, optional Node.js build) **without** relying on a bundled `.htaccess` file or checked-in `node_modules`.
- Navigate the **operator workflows** by role (system administrator, owner, HR, employee by department type).
- Understand where **screenshots** belong and how to label them for documentation or coursework.

**Related technical artifacts:** physical entity overview in [`erd.txt`](erd.txt); SQL DDL in [`sql/migrations/000_run_all.sql`](sql/migrations/000_run_all.sql); optional demo data in [`sql/seeds/001_seed_sample.sql`](sql/seeds/001_seed_sample.sql).

---

## Table of contents

1. [Quick start](#quick-start)
2. [Conceptual overview](#conceptual-overview)
3. [Planning user stories](#planning-user-stories)
4. [Roles, responsibilities, and entry URLs](#roles-responsibilities-and-entry-urls)
5. [Greenfield installation (zip, no node_modules, no .htaccess)](#greenfield-installation-zip-no-node_modules-no-htaccess)
6. [Figures and screenshots (placeholders and labeling rules)](#figures-and-screenshots-placeholders-and-labeling-rules)
7. [Operator task guides](#operator-task-guides)
8. [Developer notes](#developer-notes)
9. [Appendices](#appendices)

---

## Quick start

| Step | Action |
|------|--------|
| 1 | Install PHP (with `pdo_mysql`), MariaDB or MySQL, and optionally Node.js LTS if you will build the frontend assets. |
| 2 | Create an empty database and a database user with DDL/DML rights on that database. |
| 3 | Configure [`config/db.php`](config/db.php) via environment variables or local overrides (see [Database configuration](#database-configuration)). |
| 4 | Apply schema: run [`sql/migrations/000_run_all.sql`](sql/migrations/000_run_all.sql) against that database. Optionally load [`sql/seeds/001_seed_sample.sql`](sql/seeds/001_seed_sample.sql). |
| 5 | From the project root, install JS dependencies and build: `npm install` then `npm run build`. |
| 6 | Serve the `public/` directory with PHP’s built-in server or your web server’s document root (see [Web server without .htaccess](#web-server-without-htaccess)). |
| 7 | Open `login.php` in the browser, sign in, and use the dashboard URL for your role (see [Roles](#roles-responsibilities-and-entry-urls)). |

---

## Conceptual overview

### Problem domain

The system models an **arcade-style venue** where value is tracked in **tickets** (an internal currency), not dollars on the public screens. Guests visit **departments**: play areas (games), a gift shop (redemption counter), and customer support (membership verification).

### Departments

| Type | Typical use |
|------|-------------|
| `play_area` | Timed or session-based play; **entrance fee** in tickets; **capacity** limits concurrent sessions. |
| `gift_shop` | Catalog items priced in tickets; staff record redemptions against member or session wallets. |
| `customer_support` | Verify walk-in guests converting session wallets to member accounts. |

Each department has an **operating status** (`active`, `out_of_order`, `inactive`). Play areas may appear unavailable when **not staffed** or when the **shared ticket budget** cannot cover expected operations (see [`TicketService`](src/Services/TicketService.php)).

### Ticket ledger (high level)

Money-like flows are recorded in **`ticket_accounts`** and **`ticket_transactions`**. Important account kinds:

| Account kind | Meaning |
|----------------|---------|
| `gift_shop_budget` | **Operating pool**: admissions and owner investment increase it; **play-area payouts** and **gift-shop stocking** decrease it. |
| `gift_shop_inventory_spend` | Audit sink for cumulative **inventory procurement** (unit cost × units stocked). |
| `gift_shop_revenue` | Revenue from redemptions paid by guests’ wallets. |
| `gift_shop_investment` | Reporting counter parallel to owner investment (not the spendable pool). |
| `department_reserve` / `department_generated` | Legacy or auxiliary per-department buckets; generated tickets can be transferred toward the operating budget (owner action). |
| `member_wallet` / `session_wallet` | Balances held by members or by closed attendee sessions. |

Transaction types include admissions, payouts, redemptions, owner investment, procurement, inventory credits on stock reduction, transfers, and manual overrides—see [`erd.txt`](erd.txt) for the full list aligned with the schema.

### Key entities (non-financial)

- **Users** log in; **employees** link a user to a **department** and track shifts and HR events.
- **Attendee sessions** represent an open or closed visit to a play-area department (display name, admission mode, payout).
- **Gift shop items** hold ticket price, stock, and unit cost in tickets for stocking calculations.

For a diagram-oriented view, import or paste [`erd.txt`](erd.txt) into dbdiagram.io or Vertabelo.

---

## Planning user stories

Well-written user stories keep implementation aligned with **who** benefits and **how success is measured**.

### Recommended format

Use the classic template:

- **As a** \<role\>,
- **I want** \<capability\>,
- **So that** \<business outcome\>.

Add:

- **Acceptance criteria** (bullet list: given/when/then if helpful).
- **Data touchpoints** (which tables or flows: `attendee_sessions`, `ticket_transactions`, etc.).
- **Out of scope** notes to avoid scope creep.

### Why roles drive stories

Different roles exist to enforce **separation of duties**: an employee should not fund the venue (owner), and HR should manage personnel records without editing ticket prices (gift shop). Mapping stories to roles early avoids designing a single “super screen” that violates policy.

### Example mapping

| Story snippet | Primary role | Supporting schema / feature |
|---------------|--------------|-------------------------------|
| Admit a guest to Laser Maze | Play-area employee | `attendee_sessions`, `department_admission` → `gift_shop_budget` |
| Close session with payout | Play-area employee | `department_payout` from `gift_shop_budget` |
| Add inventory to the catalog | Gift shop employee | `gift_shop_items`, procurement transactions |
| Verify new member from session wallet | Customer support employee | `attendees`, `member_claim_transfer` |
| Increase operating credits | Owner | `owner_investment` |

---

## Roles, responsibilities, and entry URLs

After login ([`public/login.php`](public/login.php)), users are redirected by **role**. Pending registrations may be blocked until approved (see system administrator).

### System administrator (`sys_admin`)

**Handles:** Approving or rejecting new accounts where `pending_approval` applies; technical oversight.

**Why:** Separates **identity onboarding** from day-to-day arcade operations.

**Primary UI:** [`public/admin_dashboard.php`](public/admin_dashboard.php)

### Owner (`owner`)

**Handles:** Creating/updating departments; adding **owner investment** into the operating ticket pool; moving **generated** tickets from play-area buckets into the shared budget; viewing aggregates and trends.

**Why:** Capital and structural decisions stay with ownership; employees execute floor operations only.

**Primary UI:** [`public/owner_dashboard.php`](public/owner_dashboard.php)

### HR (`hr`)

**Handles:** Employee lifecycle aligned with HR flows in this codebase:

| Task | Typical entry |
|------|----------------|
| HR home / overview | [`public/hr_dashboard.php`](public/hr_dashboard.php) |
| Add employee | [`public/hr_add_employee.php`](public/hr_add_employee.php) |
| Edit employee | [`public/hr_edit_employee.php`](public/hr_edit_employee.php) |
| Transfer employee between departments | [`public/hr_transfer_employee.php`](public/hr_transfer_employee.php) |
| Terminate employee | [`public/hr_terminate_employee.php`](public/hr_terminate_employee.php) |
| Shift maintenance | [`public/hr_shifts.php`](public/hr_shifts.php) |

Employees may submit **sick-day requests** from the employee dashboard; **HR reviews** pending requests (approve/deny) on [`public/hr_dashboard.php`](public/hr_dashboard.php), which writes to `employee_sick_requests` and **HR action logs**.

**Why:** Personnel structure (department assignment, status) affects **who can clock in** for which department and thus **staffing-based availability** for play areas; sick-day workflow captures approvals with an audit trail.

### Employee (`employee`)

All employees share [`public/employee_dashboard.php`](public/employee_dashboard.php); **available actions depend on department type** (`play_area`, `gift_shop`, `customer_support`).

| Department type | Typical actions |
|-----------------|-----------------|
| Play area | Clock in/out (live shifts); open and close **attendee sessions**; admission and payout in tickets. |
| Gift shop | Manage catalog (stocking debits the operating budget per unit cost × quantity); redeem items against member or session wallets; view budget and procurement totals. |
| Customer support | Verify **member claims** linking closed sessions to new member records where applicable. |

**Why:** Floor staff actions must be **attributed** (`employee_id`, `department_id`) for auditing and reporting.

### Public / unauthenticated

**Handles:** Marketing or informational landing ([`public/index.php`](public/index.php)); registration ([`public/register.php`](public/register.php)).

---

## Greenfield installation (zip, no node_modules, no .htaccess)

These steps assume you received **only** the project sources (no `node_modules` folder) and a **new** database server.

### Prerequisites

- **PHP** 8.x recommended, with extensions: `pdo`, `pdo_mysql`, `json`, `session` (standard for this app).
- **MariaDB** or **MySQL** 10.x / 8.x compatible with InnoDB and UTF8MB4.
- **Node.js** + **npm** (only if you will compile the Vite frontend in [`frontend/`](frontend/) via [`package.json`](package.json)).
- A terminal and file permissions to create [`config/db.php`](config/db.php) or set environment variables.

### Why `node_modules` is missing

JavaScript dependencies are listed in [`package.json`](package.json) but are **not** committed. Run `npm install` once per machine to recreate them.

### Database configuration

[`config/db.php`](config/db.php) resolves credentials in this order:

1. Environment variables: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT`.
2. Optional legacy file path (see comments inside `db.php`) if present on your machine.
3. Safe defaults for local development only.

**Security:** Do not commit real production passwords. Prefer environment variables or an ignored local include.

Create the database once:

```sql
CREATE DATABASE your_arcade_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'your_user'@'%' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON your_arcade_db.* TO 'your_user'@'%';
FLUSH PRIVILEGES;
```

### Applying schema and optional seed

From the `mysql` / `mariadb` client:

```text
USE your_arcade_db;
SOURCE /absolute/path/to/sql/migrations/000_run_all.sql;
-- Optional demo data:
SOURCE /absolute/path/to/sql/seeds/001_seed_sample.sql;
```

Demo credentials (if you loaded the seed) are described **only** in the header comments of [`sql/seeds/001_seed_sample.sql`](sql/seeds/001_seed_sample.sql). Change all passwords before any production use.

### Resetting the schema (full wipe)

If you need to drop all tables and reinstall:

1. Run [`sql/reset.sql`](sql/reset.sql) (drops tables in an order compatible with foreign keys while checks are relaxed).
2. Run [`sql/migrations/000_run_all.sql`](sql/migrations/000_run_all.sql) again.
3. Optionally re-run the seed.

The application may also run **`TicketService::ensureInfrastructure()`** on first request to adjust minor schema expectations—see [`src/Services/TicketService.php`](src/Services/TicketService.php).

### Web server without `.htaccess`

This repository does **not** require Apache `mod_rewrite` rules for core routing. You only need to expose the **`public/`** directory as the HTTP document root.

**Option A — PHP built-in server (simplest for local grading or demos)**

From the **project root** (parent of `public/`):

```bash
php -S localhost:8080 -t public
```

Then open `http://localhost:8080/login.php`.

**Option B — Apache**

Point `DocumentRoot` at `.../387Project/public`. No `.htaccess` is strictly required if the server is configured to serve `public/` directly.

**Option C — Nginx + PHP-FPM**

Use a `root` directive pointing at `public/` and pass `*.php` to `php-fpm`.

### Frontend assets build

From the project root:

```bash
npm install
npm run build
```

This runs TypeScript checking and Vite; compiled assets are emitted under `public/assets/build/` for the React dashboards referenced from PHP pages.

### Troubleshooting

| Symptom | Things to check |
|---------|------------------|
| Blank white page | PHP error log; enable `display_errors` only in development. |
| Database connection error | `DB_*` env vars; database exists; user grants; host/port. |
| Missing styles or React shell | Run `npm run build`; confirm `public/assets/build/` exists. |
| “Table doesn’t exist” | Migration not applied or wrong database selected in `USE`. |

---

## Figures and screenshots (placeholders and labeling rules)

Course rubrics often reward **clear, labeled figures** tied to procedural steps. Use this convention throughout:

### File naming

Store images under a folder such as `figures/` at the project root (add the folder when you capture screenshots):

| File | Suggested content |
|------|-------------------|
| `figures/01-login.png` | Login page |
| `figures/02-owner-budget.png` | Owner dashboard ticket summary |
| `figures/03-employee-play-area.png` | Employee dashboard — play area session panel |

### Markdown pattern

Use **alt text**, the image, then a **caption**:

```markdown
![Figure 3 — Employee dashboard, play-area department.](figures/03-employee-play-area.png)

**Figure 3.** Play-area employee view showing open sessions (replace with your screenshot when available).
```

### Placeholder until images exist

Use a visible placeholder block so reviewers know what will be added:

```markdown
<!-- PLACEHOLDER FIGURE: Replace with screenshot of successful login showing role redirect URL or dashboard header. Filename: figures/01-login.png -->
![Figure 1 — PLACEHOLDER: Login screen](figures/placeholder.png)

**Figure 1.** *(To capture: browser address bar, login form, no real passwords in shot.)*
```

If you do not add `placeholder.png`, leave the caption and **TODO** note only—do not break the manual build.

### Checklist for each screenshot

- [ ] Browser **URL** visible or stated in the caption if cropped.
- [ ] **Role** or screen title visible so the figure matches the section text.
- [ ] No real production passwords or personal emails unless intentional for a **demo** tenant.
- [ ] Consistent window width (e.g. 1280px) across figures for a polished PDF export.

---

## Operator task guides

The following align with PHP routes above. Insert figures beside each subsection when you produce visuals.

### Logging in

1. Navigate to `/login.php`.
2. Enter email and password for your role.
3. Confirm redirect to the correct dashboard (`admin_dashboard.php`, `owner_dashboard.php`, `hr_dashboard.php`, or `employee_dashboard.php`).

**Figure suggestion:** Post-login owner dashboard header showing “Owner Dashboard” and ticket summary cards.

### Owner: add operating tickets (investment)

From the owner dashboard, use the **investment / increase budget** form to credit `gift_shop_budget` (and the parallel investment counter). Play-area admissions also credit this pool when sessions are recorded.

**Figure suggestion:** Owner dashboard section with investment form and before/after ticket totals (use demo numbers).

### Play-area employee: live shift and sessions

1. **Clock in** a live shift when arriving (required for staffing-driven department status in many configurations).
2. **Open session**: guest display name, admission mode (walk-in, member wallet, manual override).
3. **Close session**: payout in tickets to member wallet or session wallet as implemented.

**Figure suggestion:** Employee dashboard showing clock-in and an open session row.

### Gift shop employee: catalog and redemption

1. **Add or update items** with ticket price, **unit cost in tickets**, and stock—stocking debits the operating budget by \( \text{unit cost} \times \text{units added} \).
2. **Redeem** items by selecting the payer wallet (member or closed session).

**Figure suggestion:** Gift shop panel showing operating budget and inventory ledger totals.

### HR: add or transfer employee

Use [`hr_add_employee.php`](public/hr_add_employee.php) and [`hr_transfer_employee.php`](public/hr_transfer_employee.php) so floor employees appear under the correct **department** for permissions.

### Customer support: member verification

Use workflows on [`employee_dashboard.php`](public/employee_dashboard.php) when `department_type` is `customer_support` to complete verification flows tied to attendees.

---

## Developer notes

- **Migrations:** canonical DDL is [`sql/migrations/000_run_all.sql`](sql/migrations/000_run_all.sql).
- **ERD:** maintain [`erd.txt`](erd.txt) when adding tables or enums.
- **Backend:** [`src/Services/TicketService.php`](src/Services/TicketService.php) centralizes ticket movements and department staffing sync.
- **Frontend:** React pages live under [`frontend/src/pages/`](frontend/src/pages/); built assets are consumed from PHP under `public/`.

---

## Appendices

### Appendix A — Glossary

| Term | Definition |
|------|------------|
| Ticket | Internal numeric currency unit used across admissions, payouts, and gift shop flows (not USD unless explicitly stated elsewhere). |
| Operating budget | Balance in `gift_shop_budget` shared by payouts and procurement. |
| Session wallet | Ticket balance attached to a closed attendee session until claimed or spent. |

### Appendix B — FAQ

**Do I need Node.js on the server?** Only if you rebuild assets after changing `frontend/`. A deployed server can run PHP-only if assets are pre-built.

**Do I need `.htaccess`?** No, provided `public/` is the web root.

**Where is the database password stored?** In environment variables or [`config/db.php`](config/db.php) resolution chain—not in this manual.

### Appendix C — Figure index (fill in as you add images)

| Fig. | Filename | Section |
|------|----------|---------|
| 1 | `figures/01-login.png` | Logging in |
| 2 | `figures/02-owner-budget.png` | Owner investment |
| 3 | `figures/03-employee-play-area.png` | Play-area employee |
| 4 | `figures/04-gift-shop.png` | Gift shop |

*(Extend this table for coursework submissions.)*

---

*End of manual.*
