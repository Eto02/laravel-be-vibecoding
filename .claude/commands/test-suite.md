---
description: Run the full test suite, categorize each failure by root cause type, and offer targeted fixes in priority order
---

Run and analyze the test suite for this Laravel 13 API project.

Arguments: $ARGUMENTS
- If empty: run all tests
- If contains a class name: run only that class with `--filter`
- If contains "coverage": run with `--coverage --min=80`
- If contains "unit": run only the Unit suite
- If contains "feature": run only the Feature suite

## Step 1: Prepare and Run

```bash
php artisan config:clear 2>&1 && php artisan test --stop-on-failure=false 2>&1
```

If you see "PDO" or "database" errors, the SQLite test DB may not be initialized. Try:
```bash
php artisan test --env=testing 2>&1
```

For coverage:
```bash
php artisan test --coverage --min=80 2>&1
```

## Step 2: Categorize Each Failure

For every failing test, assign exactly one category:

**Category A — Infrastructure / Setup**
Symptoms: "Class not found", "PDO exception", "Connection refused", missing env variable, undefined method on null
Root cause: something is broken before test logic runs
Fix location: config files, AppServiceProvider, bootstrap, missing class files

**Category B — Response Structure Mismatch**
Symptoms: assertJson fails on `success` key, assertStatus gets 200 instead of 201, missing `meta` key
Root cause: controller uses legacy `response()->json()` instead of `ApiResponse`, or wrong status code
Fix location: the controller class

**Category C — Validation Logic**
Symptoms: 422 when 200 expected, or 200 when 422 expected, wrong `errors` key in response
Root cause: FormRequest rules are wrong, or a field is missing/extra
Fix location: the FormRequest class

**Category D — Business Logic**
Symptoms: wrong value returned, wrong DB state after action, wrong relationship loaded
Root cause: Service method returns incorrect result
Fix location: the Service class

**Category E — Test Setup**
Symptoms: "Auth user is null", factory method doesn't exist, `actingAs` missing, `RefreshDatabase` not used
Root cause: the test file itself is wrong, not the application code
Fix location: the test file

## Step 3: Output Per Failure

For each failing test:
```
FAIL: {ClassName}::{test_method_name}
Category: {A | B | C | D | E}
Error: {exact PHPUnit error message}
Root cause: {one sentence}
Fix file: {path/to/file.php}
Fix: {exact code change — show old line and new line}
---
```

## Step 4: Summary

```
RESULTS
Tests run:  N
Passing:    N
Failing:    N

Failure breakdown:
  A (Infrastructure):     N
  B (Response structure): N
  C (Validation):         N
  D (Business logic):     N
  E (Test setup):         N
```

## Step 5: Offer Fixes by Priority

Fix in this priority order (higher categories block lower ones):
1. A first — nothing else passes if infrastructure is broken
2. E second — test code errors, not app errors
3. B third — systematic response structure fixes often unblock many tests at once
4. C fourth — validation rule adjustments
5. D last — most case-specific, least likely to have cascading effect

Offer: "Would you like me to fix all Category A and B issues now? These are the highest-leverage fixes."

## Step 6: Coverage Report (if --coverage requested)

List all files below the 80% threshold:
```
LOW COVERAGE FILES (< 80%)
app/Services/OrderService.php      — 42% (8/19 lines covered)
app/Http/Controllers/Api/...       — 61% (14/23 lines covered)
```

Suggest which tests to add for the top 3 lowest-coverage files.
