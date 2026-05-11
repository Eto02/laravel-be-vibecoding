---
description: Reset and reseed the dev database — runs migrate:fresh --seed via docker compose. WARNING: drops all data.
---

Reset and reseed the development database.

## Pre-flight

1. Check if Docker is up: `docker compose ps app | grep -q running` — fail fast with friendly message if not.
2. Warn user: this will **drop all data** in the dev DB. If user has not already confirmed in their prompt, ask for confirmation before proceeding.

## Run

3. Execute: `docker compose exec app php artisan migrate:fresh --seed`
4. Capture the output and surface:
   - Number of migrations run
   - Whether DevSeeder completed successfully
   - The credentials table printed by DevSeeder (Admin, Merchant, Buyer emails + password)

## Verify

5. Quick sanity check: `docker compose exec app php artisan tinker --execute="echo App\\Models\\User::count() . ' users, ' . App\\Models\\Product::count() . ' products, ' . App\\Models\\Order::count() . ' orders';"`
6. Report the counts so the user can confirm seeding worked.

## Notes

- Default credentials seeded:
  - Admin: `admin@marketplace.dev` / `password123`
  - Merchant: `merchant@marketplace.dev` / `password123`
  - Merchant2: `merchant2@marketplace.dev` / `password123`
  - Buyer: `test@example.com` / `password123`
- This command does NOT run tests — use `/test-suite` separately.
- Use this command after schema changes, demo prep, or when DB state is confusing.
