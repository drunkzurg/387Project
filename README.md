# Arcade Management System (Scaffold)

Tech: PHP + MariaDB + React (Vite) + TypeScript + Tailwind.

## Structure
- `public/` web root (index.php, assets)
- `src/` PHP code (controllers/models/auth/db)
- `frontend/` React dashboard UI (built into `public/assets/build/`)
- `templates/` PHP templates
- `sql/` migrations + seeds
- `config/` runtime configuration
- `docs/` documentation (including ERD copy)
- `storage/` runtime files (logs)

## Next
## Database setup (MariaDB)

DB name: `bpaudel2`

1) (Optional) reset tables
- Run `sql/reset.sql`

2) Apply migrations
- Option A (recommended): run `sql/migrations/000_run_all.sql`
- Option B: run all files in `sql/migrations/` in order (001 -> 011)

3) Load sample data
- Run `sql/seeds/001_seed_sample.sql`

Seed user logins (all passwords are `password`):
- `admin@arcade.local` (sys_admin)
- `owner@arcade.local` (owner)
- `hr@arcade.local` (hr)
- `employee@arcade.local` (employee)

## Password hashing helper

Generate a bcrypt hash for a password:

- `php scripts/hash_password.php password`
- `echo -n "password" | php scripts/hash_password.php -`

https://turing.cs.olemiss.edu/~bpaudel2/387Project/public/index.php?db_test=1
