# Database (Migrations + Seeds)

## Migrations
Migrations live in `sql/migrations/` and should be run in filename order.

**Apply migrations (manual):**
1. Connect to MariaDB
2. `USE bpaudel2;`
3. Run each file in `sql/migrations/` in order

## Seeds
Seed files live in `sql/seeds/`.

**Load sample data:**
- Run `sql/seeds/001_seed_sample_data.sql` after migrations.

## Notes
- The schema is based on `docs/erd.txt`.
- Seed users all share password: `password`.
