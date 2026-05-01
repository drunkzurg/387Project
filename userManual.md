# Arcade Management System — User & Operator Manual

## 1. Purpose and audience

This manual is for **operators deploying the app**, **course staff grading the project**, and **demo users** who need to exercise every role in the ticket-based arcade workflow.

After following Sections 2–6 you will be able to install the stack, load the database, build the UI assets, and verify that PHP can reach MariaDB. Sections 7–8 explain **who logs in where**, which **business behaviors** each dashboard exposes, and how the **public member wallet** and **debug toolbar** fit in.

**Scope:** The application is a PHP backend with MariaDB, plus React (Vite + TypeScript) “islands” embedded from PHP. The web server must serve the **`public/`** folder as the document root. Dashboards live at role-specific `.php` pages under `public/`; each page mounts a React bundle built from `frontend/src/main.tsx`.

---

## Table of contents

1. [Purpose and audience](#1-purpose-and-audience)
2. [Repository layout and prerequisites](#2-repository-layout-and-prerequisites)
3. [Environment configuration](#3-environment-configuration)
4. [Database: reset, migrate, seed](#4-database-reset-migrate-seed)
5. [Frontend build and assets](#5-frontend-build-and-assets)
6. [Running and verifying the application](#6-running-and-verifying-the-application)
7. [Authentication and registration](#7-authentication-and-registration)
8. [Personas, dashboards, and behaviors](#8-personas-dashboards-and-behaviors)
9. [Figures and screenshots (global rules)](#9-figures-and-screenshots-global-rules)
10. [Troubleshooting](#10-troubleshooting)

---

## 2. Repository layout and prerequisites

### Clone and top-level folders

Clone the project to your machine (replace `<repository-url>` with your course or hosting URL):

```bash
git clone <repository-url> 387Project
cd 387Project
```

- **`public/`** — Document root for Apache/Nginx or `php -S`. Contains `index.php`, dashboard PHP pages, and built assets under `public/assets/build/`.
- **`config/db.php`** — Database credentials resolution (environment variables with safe defaults).
- **`sql/`** — `reset.sql`, `migrations/`, `seeds/` for schema and demo data.
- **`frontend/`** — Vite + React + Tailwind source; build output is written into `public/assets/build/`.
- **`src/`** — Shared PHP (Auth, Database, TicketService, Debug toolbar, views).

### Prerequisites

| Requirement | Notes |
|-------------|--------|
| PHP | CLI for migrations/smoke tests; `pdo_mysql` enabled for the web SAPI. |
| MariaDB or MySQL | Client to run `SOURCE` scripts. |
| Node.js + npm | To install and build the frontend (`frontend/package.json`). |

Replace `/absolute/path/to/387Project` below with your clone path.

---

## 3. Environment configuration

PHP reads settings from **`config/db.php`**. Resolution order:

1. **Environment variables** (recommended): `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT`.
2. Optional legacy credential file on the author machine only (ignore on new installs).
3. **Fallbacks** if variables are missing: host `localhost`, port `3306`, database `bpaudel2`, user `root`, empty password.

### Option A — Shell exports (works with PHP built-in server)

```bash
export DB_HOST=localhost
export DB_PORT=3306
export DB_NAME=your_database_name
export DB_USER=your_user
export DB_PASS='your_password'
```

### Option B — Apache `.htaccess` (example)

**Where to place it:** Create or edit **`public/.htaccess`** — the same directory as **`public/index.php`**, because Apache applies directory-level overrides relative to the document root.

Requirements:

- `AllowOverride All` (or equivalent) for that directory so `.htaccess` is processed.
- `mod_env` enabled so `SetEnv` works.

Example file (adjust names/passwords; do not commit real production secrets to public repos):

```apache
<IfModule mod_env.c>
  SetEnv DB_HOST localhost
  SetEnv DB_PORT 3306
  SetEnv DB_NAME your_database_name
  SetEnv DB_USER your_user
  SetEnv DB_PASS your_password
</IfModule>
```

### Option C — Apache VirtualHost

If `.htaccess` is disabled, set the same variables inside the `<VirtualHost>` with `SetEnv` (see internal deployment notes).

### Option D — Nginx + php-fpm

Set PHP environment variables in the **php-fpm pool** configuration for `DB_*`, not only in the Nginx server block. Point `root` at `public/`.

> **Figure 1 (TODO):** `docs/figures/01-htaccess-location.png`  
> **Capture:** Your IDE file tree or terminal listing showing `387Project/public/.htaccess` next to `index.php`.  
> **Mark up:** **A** = path `public/.htaccess`, **B** = `SetEnv` lines for `DB_HOST` / `DB_NAME`, **C** = `index.php` in the same folder.

---

## 4. Database: reset, migrate, seed

### Clean database (optional but recommended before first full install)

From the `mysql` / `mariadb` client, select your database and run:

```sql
SOURCE /absolute/path/to/387Project/sql/reset.sql;
```

`reset.sql` drops application tables in a foreign-key-safe order.

### Apply full schema

```sql
SOURCE /absolute/path/to/387Project/sql/migrations/000_run_all.sql;
```

### Upgrading an existing database

If you already ran an older migration pack, apply **new** patch files in numeric order under `sql/migrations/` (for example `013_drop_attendees_created_at.sql`) as described in `sql/README.md`.

### Load demo seed data

```sql
SOURCE /absolute/path/to/387Project/sql/seeds/001_seed_sample.sql;
```

### One-shot “from zero” sequence

```sql
USE your_database_name;
SOURCE /absolute/path/to/387Project/sql/reset.sql;
SOURCE /absolute/path/to/387Project/sql/migrations/000_run_all.sql;
SOURCE /absolute/path/to/387Project/sql/seeds/001_seed_sample.sql;
```

### Seed accounts (demo password)

All seeded users share password: **`password`** (bcrypt in the seed file).

Useful approved accounts:

| Email | Role |
|-------|------|
| `admin@arcade.local` | `sys_admin` |
| `owner@arcade.local` | `owner` |
| `hr@arcade.local` | `hr` |
| `employee@arcade.local` | `employee` (play area – Retro Arcade, seed) |
| `giftshop@arcade.local` | `employee` (Gift Shop) |
| `support@arcade.local` | `employee` (Customer Support) |
| `laser@arcade.local`, `pinball@arcade.local`, … | `employee` (other play areas) |

Pending (not approved) seed accounts such as `pending.owner@arcade.local` exist for **admin approval** demos.

> **Figure 2 (TODO):** `docs/figures/02-database-source-sequence.png`  
> **Capture:** Terminal or SQL client showing the three `SOURCE` commands (reset → `000_run_all.sql` → `001_seed_sample.sql`) with no errors.  
> **Mark up:** **A** = `reset.sql`, **B** = `000_run_all.sql`, **C** = `001_seed_sample.sql`.

---

## 5. Frontend build and assets

From **`frontend/`**:

```bash
npm ci
# or: npm install
npm run build
```

Build output goes to **`public/assets/build/`**, including **`public/assets/build/.vite/manifest.json`**. PHP loads hashed CSS/JS via **`src/View/FrontendAssets.php`**.

If the manifest is **missing**, pages fall back to legacy **`public/assets/js/app.js`** and **`public/assets/css/app.css`** when present — useful if you ship a bundle without running Vite, but **dashboard styling and React islands expect a successful build** for this project.

> **Figure 3 (TODO):** `docs/figures/03-npm-run-build.png`  
> **Capture:** Terminal showing completed `npm run build` with exit code 0.  
> **Mark up:** **A** = working directory `frontend`, **B** = `vite build` success line, **C** = output path mentioning `public/assets/build`.

---

## 6. Running and verifying the application

### PHP built-in server (development)

From the **project root** (parent of `public/`):

```bash
export DB_HOST=localhost
export DB_PORT=3306
export DB_NAME=your_database_name
export DB_USER=your_user
export DB_PASS='your_password'
php -S localhost:8080 -t public
```

Open `http://localhost:8080/index.php`.

### Database smoke test

Open:

`http://localhost:8080/index.php?db_test=1`

Expected plain-text response: **`DB connection: OK`**.

The public home footer also links to this probe (`index.php?db_test=1`).

> **Figure 4 (TODO):** `docs/figures/04-db-test-ok.png`  
> **Capture:** Browser showing URL `.../index.php?db_test=1` and body text `DB connection: OK`.  
> **Mark up:** **A** = URL bar, **B** = response text.

---

## 7. Authentication and registration

| Page | Purpose |
|------|---------|
| **`public/login.php`** | Email/password login; session-backed. |
| **`public/logout.php`** | Ends session. |
| **`public/register.php`** | Creates a **pending** user; roles allowed: `employee`, `owner`, `hr`. |

Until **`pending_approval`** is cleared by a **system admin**, the public home treats the account as pending: staff dashboard shortcuts stay hidden even if the user is logged in (`index.php` checks the flag).

**Staff login modal on the home page** (`public-home.tsx`) posts through **`auth_modal.php`** for login / account request flows tied to the marketing layout.

> **Figure 5 (TODO):** `docs/figures/05-register.png`  
> **Capture:** `register.php` in the browser.  
> **Mark up:** **A** = role selector, **B** = password fields, **C** = submit control.

---

## 8. Personas, dashboards, and behaviors

Staff roles receive **`employee_dashboard.php`** but **sections differ by `department_type`** (`play_area`, `gift_shop`, `customer_support`). Data comes from PHP props into React (`employee-dashboard.tsx`).

### How department models connect (short reference)

| Department type | Core ideas |
|-----------------|------------|
| **Play area** | **`attendee_sessions`** rows; **`session_wallet`** ticket account while guests play; open/close drives payouts to member wallet or session wallet. |
| **Gift shop** | **`gift_shop_items`** catalog; **`gift_shop_redemptions`** debit **`member_wallet`** or a **closed** session’s **`session_wallet`** via ticket ledger accounts (`gift_shop_budget`, etc.). |
| **Customer support** | Verified **`attendees`** / **`member_wallet`**; **claim** converts eligible closed sessions into verified members when rules match. |

---

### 8.1 System administrator (`sys_admin`)

- **Goals:** Onboard staff by approving registrations; maintain the user directory.
- **Navigate:** Log in → **`admin_dashboard.php`** → React **Admin Dashboard** (`admin-dashboard.tsx`).
- **Sections:** **Pending Accounts** (approve/reject); directory of existing users (includes delete flows where implemented).

Approve pending users so they appear in the **debug toolbar** impersonation list (only **`pending_approval = 0`** users are listed).

> **Figure 6 (TODO):** `docs/figures/06-admin-pending.png`  
> **Capture:** Admin Dashboard — pending queue with actions visible.  
> **Mark up:** **A** = pending row, **B** = Approve, **C** = Reject.

---

### 8.2 Owner (`owner`)

- **Goals:** Steer departments and ticket-economy investment; read aggregate circulation and activity.
- **Navigate:** **`owner_dashboard.php`** → **Owner Dashboard** (`owner-dashboard.tsx`).
- **Behaviors:** Summary stats (credits, circulation, gift shop metrics, active attendees); **department trend** chart; **activity** chart; **recent transactions** and **investment logs**; **create/edit departments** (types: play area, gift shop, customer support — entrance fee, capacity, operating status, description); **gift shop budget / investment** style actions that credit operating pools.

> **Figure 7 (TODO):** `docs/figures/07-owner-departments.png`  
> **Capture:** Owner dashboard showing department create/edit and at least one department row.  
> **Mark up:** **A** = department type, **B** = entrance fee, **C** = operating status, **D** = save/submit.

> **Figure 8 (TODO):** `docs/figures/08-owner-investment.png`  
> **Capture:** Gift shop budget / ticket investment controls and summary numbers.  
> **Mark up:** **A** = ticket amount input, **B** = submit action, **C** = displayed operating budget or summary stat.

---

### 8.3 HR (`hr`)

- **Goals:** Maintain HR records, approve sick time, monitor weekly hours for operational staff.
- **Navigate:** **`hr_dashboard.php`** → **HR Dashboard** (`hr-dashboard.tsx`).
- **Behaviors:** **Employee directory** with department assignment, wage, status; **weekly hour bars** and recent shifts per employee; **sick requests** (approve/deny + review notes); **HR action log** audit trail.

> **Figure 9 (TODO):** `docs/figures/09-hr-employees.png`  
> **Capture:** Employee table or edit modal.  
> **Mark up:** **A** = department, **B** = hourly wage, **C** = status, **D** = save.

> **Figure 10 (TODO):** `docs/figures/10-hr-sick-requests.png`  
> **Capture:** Sick requests section.  
> **Mark up:** **A** = status, **B** = approve, **C** = deny, **D** = review notes.

---

### 8.4 Play area employee (`employee`, department `play_area`)

- **Goals:** Track own shifts; run attendee sessions and ticket payouts for the floor.
- **Navigate:** **`employee_dashboard.php`** → sections **Employee**, **Department**, **Play Area Operations**.
- **Behaviors:** Clock **in/out** on live shifts; optional manual shift entry; **sick day requests**; **open session** (display name, admission mode, optional linked member); **close session** with payout tickets — feeds **`TicketService`** ledger rules (member vs walk-in session wallet).

> **Figure 11 (TODO):** `docs/figures/11-employee-shifts.png`  
> **Capture:** Employee + shift history / clock controls.  
> **Mark up:** **A** = clock in, **B** = clock out, **C** = shift table.

> **Figure 12 (TODO):** `docs/figures/12-employee-play-area.png`  
> **Capture:** Play Area Operations — open/close session UI.  
> **Mark up:** **A** = open session form, **B** = active sessions, **C** = close/payout controls.

---

### 8.5 Gift shop employee (`employee`, department `gift_shop`)

- **Goals:** Maintain prizes for tickets; redeem items against member or session wallets.
- **Navigate:** Same **`employee_dashboard.php`**, sections **Gift Shop Operations**.
- **Behaviors:** Create/update catalog items; redeem with **source** tied to `member_wallet` or closed **`session_wallet`** (internal `source_token` format aligns PHP queries with the POST handler); view recent redemptions.

> **Figure 13 (TODO):** `docs/figures/13-employee-gift-shop-catalog.png`  
> **Capture:** Catalog / item management area.  
> **Mark up:** **A** = item fields, **B** = stock or status, **C** = save.

> **Figure 14 (TODO):** `docs/figures/14-employee-gift-shop-redeem.png`  
> **Capture:** Redemption form with wallet source and quantity.  
> **Mark up:** **A** = ticket source, **B** = item, **C** = redeem submit.

---

### 8.6 Customer support employee (`employee`, department `customer_support`)

- **Goals:** Verify members and walk guests through claims from leftover session wallets.
- **Navigate:** **`employee_dashboard.php`** → **Members** (roster) and **Customer Support Claims**.
- **Behaviors:** Review verified members; complete **claim** workflow for eligible closed sessions (session wallet balance, no attendee linked) to create verified attendee + **member wallet** per server rules.

> **Figure 15 (TODO):** `docs/figures/15-employee-support-claim.png`  
> **Capture:** Claims section with member fields / submit.  
> **Mark up:** **A** = candidate session row, **B** = identity fields, **C** = confirm claim.

---

### 8.7 Member (public — no staff login)

- **Goals:** Check ticket balance and history using only a membership code.
- **Navigate:** **`index.php`** → **Live Department Board**, membership lookup card (calls **`member_wallet_lookup.php`** via POST from `public-home.tsx`).
- **Demo codes:** Seed includes **`MEM-1001`** through **`MEM-1004`** (see `sql/seeds/001_seed_sample.sql`).

> **Figure 16 (TODO):** `docs/figures/16-public-member-wallet.png`  
> **Capture:** Home page after a successful lookup — balance + recent transactions table.  
> **Mark up:** **A** = membership code field, **B** = balance, **C** = transaction rows.

---

### 8.8 Potential buyer / grader (debug toolbar)

- **Goals:** Quickly assume any **approved** user without juggling passwords during a demo.
- **Navigate:** Yellow **DBG** button (fixed bottom-right on pages that render the toolbar). Open panel → choose **Login as user** — posts `debug_toolbar_action` / `debug_toolbar_user_id`.
- **Routing:** After impersonation, PHP redirects by role — `sys_admin` → `admin_dashboard.php`, `owner` → `owner_dashboard.php`, `hr` → `hr_dashboard.php`, `employee` → `employee_dashboard.php` (`src/Debug/DebugToolbar.php`).
- **Constraint:** Only users with **`pending_approval = 0`** appear. Pending seed accounts require admin approval first.

> **Figure 17 (TODO):** `docs/figures/17-debug-toolbar.png`  
> **Capture:** Home or dashboard with DBG panel open showing user list.  
> **Mark up:** **A** = DBG button, **B** = impersonation target row, **C** = current user line in panel header.

---

## 9. Figures and screenshots (global rules)

- Save images under **`docs/figures/`** with stable names matching this manual (rename if your numbering differs—keep captions in sync).
- Use a **consistent window size** (for example 1280×720) for PDF export.
- Include the **browser URL** in screenshots when it clarifies which `.php` page is shown.
- **Redact** real passwords; demo screenshots should use seed **`password`** only in controlled environments.
- For each figure, repeat the **TODO block** from Sections 3–8 when you export the final doc: filename, what to capture, and labels **A / B / C** for annotations.

---

## 10. Troubleshooting

| Symptom | What to check |
|---------|----------------|
| **`DB connection: ERROR`** at `?db_test=1` | Env vars, `config/db.php`, database exists, user grants, MariaDB running. |
| **React islands blank or unstyled** | Run `npm run build` in `frontend/`; confirm `public/assets/build/.vite/manifest.json` exists. |
| **Debug toolbar user list empty** | Run seeds; approve pending users in **Admin Dashboard**. |
| **Cannot impersonate an account** | User must be **approved** (`pending_approval = 0`). |

---

## Appendix: Quick URL map

| URL | Role / use |
|-----|------------|
| `index.php` | Public home, member wallet lookup |
| `login.php` / `logout.php` | Session auth |
| `register.php` | Staff signup (pending) |
| `admin_dashboard.php` | `sys_admin` |
| `owner_dashboard.php` | `owner` |
| `hr_dashboard.php` | `hr` |
| `employee_dashboard.php` | `employee` (department-specific sections) |

Schema details: **`docs/erd.txt`** (mirror **`erd.txt`**) and **`sql/README.md`**.
