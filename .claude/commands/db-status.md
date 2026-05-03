---
description: Check migration status, DB connectivity, schema completeness against marketplace requirements, missing indexes, and Redis health
---

Perform a full database and infrastructure health check for this Laravel 13 marketplace API.

## Step 1: MySQL Connectivity

```bash
php artisan db:show 2>&1
```

If this fails:
- Check `.env` values: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` (do NOT print `DB_PASSWORD`)
- Check if the MySQL container is running: `docker compose ps`
- If not running: "Run `docker compose up -d mysql` to start the MySQL container"

## Step 2: Migration Status

```bash
php artisan migrate:status 2>&1
```

Parse and report:
- Total migrations run
- Total pending
- For each pending migration: filename + inferred purpose from filename

If all up to date: "All migrations are current."

## Step 3: Schema Drift Check

Compare existing tables against the expected marketplace schema. Check each table:

**Core tables (required for auth system to work):**
- `users`
- `personal_access_tokens`
- `refresh_tokens`
- `oauth_accounts`

**Infrastructure tables (required for Laravel features):**
- `cache`
- `jobs`
- `failed_jobs`

**Marketplace tables (required for business features):**
- `vendors`
- `categories`
- `products`
- `orders`
- `order_items`
- `payments`
- `reviews`

Use `php artisan db:show` or tinker to list existing tables:
```bash
php artisan tinker --execute="echo implode(', ', \Illuminate\Support\Facades\Schema::getTables()->pluck('name')->toArray());" 2>&1
```

Output format:
```
SCHEMA COVERAGE
Core tables (4):        ✓ users  ✓ personal_access_tokens  ✓ refresh_tokens  ✓ oauth_accounts
Infrastructure (3):     ✓ cache  ✓ jobs  ✗ failed_jobs
Marketplace tables (7): ✗ vendors  ✗ categories  ✗ products  ✗ orders  ✗ order_items  ✗ payments  ✗ reviews
```

## Step 4: Index Audit

For each existing table, flag potential missing indexes. Warn if:
- Any column ending in `_id` (FK columns) has no index
- Any `status` column has no index
- Any `email` column lacks a UNIQUE index
- Any `token` column lacks a UNIQUE index
- The `orders` table lacks an index on `user_id` and `status` combined

Run per-table inspection:
```bash
php artisan db:table users 2>&1
php artisan db:table refresh_tokens 2>&1
```

## Step 5: Redis Health

```bash
php artisan tinker --execute="var_dump(\Illuminate\Support\Facades\Redis::ping());" 2>&1
```

If this fails:
- Check if Redis container is running: `docker compose ps`
- If not: "Run `docker compose up -d redis` to start the Redis container"
- Check `.env`: `REDIS_HOST`, `REDIS_PORT`

## Step 6: Queue Health

```bash
php artisan queue:monitor 2>&1 || php artisan tinker --execute="echo \Illuminate\Support\Facades\Queue::size();" 2>&1
```

Report queue size and whether a worker is expected to be running.

## Step 7: Final Summary Report

Output a concise status block:

```
DATABASE STATUS REPORT — {current datetime}

Connections:
  MySQL:    [CONNECTED | UNREACHABLE — reason]
  Redis:    [CONNECTED | UNREACHABLE — reason]

Migrations:
  Run:      N
  Pending:  N  [list pending filenames if any]

Schema Coverage:
  Core:           N/4 tables present
  Infrastructure: N/3 tables present
  Marketplace:    N/7 tables present
  Missing:        [list missing tables]

Index Warnings:   N potential issues
  [list each warning]

Recommended next steps:
  1. [highest priority action — e.g. "Create failed_jobs migration"]
  2. [second action]
  3. [third action if any]
```
