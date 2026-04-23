# Database (Migrations + Seeds)

## Migrations
The project uses a single bootstrap migration file that creates the full ticket-economy schema.

**Apply migrations (manual):**
1. Connect to MariaDB
2. `USE bpaudel2;`
3. Run `SOURCE /home/bpaudel2/public_html/387Project/sql/migrations/000_run_all.sql;`

## Seeds
Seed files live in `sql/seeds/` and the sample seed loads a demo-ready ticket economy.

**Load sample data:**
- Run `SOURCE /home/bpaudel2/public_html/387Project/sql/seeds/001_seed_sample.sql;` after migrations.

## Notes
- Use `sql/reset.sql` before re-running migrations from scratch.
- The schema is documented in `docs/erd.txt` and `erd.txt`.
- Seed users all share password: `password`.
