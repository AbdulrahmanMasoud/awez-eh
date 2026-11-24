# Report 001: Project skeleton and quality gate

- PR: #1 (https://github.com/AbdulrahmanMasoud/awez-eh/pull/1). CI: green on every push of the branch (Pint, Rector, Larastan and Pest, each its own step).
- Branch: task/001-project-skeleton. Commit: 4d23997 (the last before this report).

## What changed

- The repository is now a working Laravel 13 application, and the plan and docs are kept as they were. A developer can clone it and start everything with three commands (README › Getting started).
- `sail up -d --wait` starts the app at http://localhost:8020, PostgreSQL 18 with PostGIS, and Redis. The ports don't clash with SellBubble's, so both run on the same laptop.
- PostgreSQL has two databases: `awez_eh` for the owner's demo data and `testing` for the tests. The tests never touch the demo data and never use SQLite.
- `sail composer check` is the gate: formatting, Rector, static analysis at the strictest level, and the tests. GitHub runs the same checks on every push and pull request.
- Users get UUID keys from the first migration, so nothing has to be migrated later.

## Decisions for the owner

1. **The PostGIS image is amd64 only.** `postgis/postgis` publishes no arm64 build for PostgreSQL 18, so on an Apple silicon Mac it runs emulated (`platform: linux/amd64` in `compose.yaml`).
    - Done meanwhile: the official image, as the brief asks. The cost is small: the full suite takes about 3 s, `up --wait` about 30 s, and only the first `CREATE EXTENSION postgis` is slow (about 1 s).
    - The other way: a community image or one we build ourselves, which would be faster locally but means one more thing to maintain, and an image that differs from CI's.
2. **`composer.json` requires PHP `^8.5`**, where Laravel ships with `^8.3`.
    - Done meanwhile: `^8.5`, the stack's version. It also makes Rector apply the PHP 8.5 rules.
    - Cost of going back to `^8.3`: Rector would stop at 8.3. The only gain is installing on PHP 8.3 or 8.4, which this project does not use.
3. **CI runs on every push and every pull request**, as the brief says, so each push to a branch with an open PR runs it twice (about 45 s each).
    - The other way is SellBubble's: on pushes to `main` only, plus pull requests. It saves Actions minutes, but a branch without a PR then goes unchecked.
4. **The `users` table is still Laravel's** (`name`, `email`, `password`), with only its key changed to UUID. Step 1.4 (Google and Apple sign-in) will reshape it.
    - "Never edit a merged migration" means 1.4 would add a second migration. Since nothing is deployed yet, you could instead let 1.4 edit this one, which keeps the history short.
    - Please say which before 1.4 starts.

## The owner's steps

1. **Merge #2 (sign-in with Google and Apple, maps on OpenStreetMap) first, then this PR.**
    - Both carry the same `DECISIONS.md` (entries 1–18) and the same brief, so they merge without conflicts in either order.
    - #2 first keeps `main` consistent: this PR's brief and its entries 9–18 build on #2's entries 7 and 8.
2. **After merging, in the main checkout:**
    - `git pull`, then follow README › Getting started.
    - To use Boost there, run `vendor/bin/sail artisan boost:install --skills --mcp`. Its files are git-ignored, as in SellBubble.
3. The first `sail up` builds the PHP 8.5 Sail image, which took about 10 minutes here. Later starts reuse it.

No DNS, Dokploy or production settings yet.

## Verification

**Tests added (21 tests, 72 assertions):**

- `tests/Feature/Infrastructure/DatabaseTest.php`:
    - the driver is `pgsql` and the server is version 18;
    - the database is `testing`, or a parallel worker's `testing_test_N`;
    - `pg_extension` has `postgis` and `pg_trgm`;
    - `ST_Distance` between `POINT(0 0)` and `POINT(1 0)` as `geography` is 111,319.49 m;
    - trigram `similarity('صيدلية', 'صيدليه')` is above 0.3.
- `RedisTest` (Redis answers `PING`), `OutboundHttpTest` (an un-faked call throws `StrayRequestException`; a faked one passes), and `HealthTest` (`GET /up` is 200).
- `tests/Feature/Users/PrimaryKeysTest.php`: user ids are UUID v7, and Sanctum issues a token to a UUID user.
- `tests/Arch/PresetsTest.php`: the Pest presets `php`, `security` and `laravel`. The laravel preset ignores domain layers, as in SellBubble.
- `tests/Arch/ConventionsTest.php`:
    - strict types in `App`, `Database` and `Tests`;
    - no `dd`, `ddd`, `dump`, `ray` or `var_dump`;
    - `env()` only in `config/`;
    - Actions are final with `handle()`;
    - `App\Domains` does not use `App\Http` or `App\Filament`;
    - controllers don't use `DB`;
    - jobs implement `ShouldQueue`.
    
    Rules about domain layers find their namespaces from the folders under `app/Domains`, so each one applies as soon as its first class appears.

**Tests first.** Before Sanctum was installed the suite had 19 passed, 1 failed and 1 error:

- the skeleton's `Controller.php` had no `strict_types`;
- `createToken()` was undefined on `User`.

**`sail composer check`:** Pint passed; Rector made 0 changes; Larastan at max found 0 errors with no baseline; Pest had 21 passed with 72 assertions, in parallel. The same result came from a fresh clone, and on CI (run 45406086297: 21 passed, 4 processes, job 43 s).

**Arch tests catch violations.** Each throwaway was added, run, then removed (the suite was green again, 10/10):

| Throwaway | Arch test that failed |
| --- | --- |
| A non-final `App\Domains\Throwaway\Actions\DoSomething` | "actions are final and expose handle()" |
| An `env('APP_NAME')` call in `App\Support\Throwaway` | "env() is read only in config files", and the laravel preset |
| A `dd($value)` in `App\Support\Throwaway` | "debug helpers are never committed", and the laravel preset |

**Migrations on `testing`:**

- `migrate:fresh --seed` ran all 5 migrations and seeded 1 user.
- `migrate:rollback --step=5` left only the `migrations` table and `plpgsql`.
- `migrate` brought back `postgis` and `pg_trgm`. Afterwards `users.id` and `personal_access_tokens.tokenable_id` are `uuid`, and `sessions_user_id_foreign` cascades.

**Fresh clone.** Cloned from GitHub into a separate folder, then: `cp .env.example .env`, the one-time `composer install` in `composer:2`, `sail up -d --wait` (32 s), and `sail composer setup`.

- Result: `awez_eh` has 5 migrations, 1 user and both extensions; `testing` has 5 migrations and both extensions; `curl -i localhost:8020/up` returns `HTTP/1.1 200 OK`. The clone's stack and volumes were removed afterwards.
- The first attempt, with plain `sail up -d`, failed: `migrate` met PostgreSQL while it was still running its first-start init (connection refused). The fix is commit 3e3adf5, with the reason in `DECISIONS.md` entry 17.

**`sail composer dev`** starts only `queue` (`queue:listen`) and `logs` (`pail`), through Laravel 13's `artisan dev`.

## Not done and follow-ups

- **The literal sequence is `sail up -d --wait`**, not the brief's `sail up -d`: without `--wait`, a script that runs `setup` straight away meets a database that is still starting (see Verification).
- **A fresh clone needs one `composer install`** in Composer's image before `sail` exists, because Sail lives in `vendor/`.
- **CLAUDE.md lists "controllers return API Resources" as an arch rule. It isn't written yet:** there are no controllers to shape it on. It fits step 1.2, with the `/api/v1` group.
- **`routes/api.php` is empty.** Laravel's `GET /api/user` closure returned the model directly, against invariant 10. The `/api/v1` group comes in 1.2.
- **Laravel's welcome page still answers at `/`**, and its frontend files are kept (`DECISIONS.md` entry 18).
- **Two task worktrees can't run their stacks at once** with the default ports. Give the second one other ports in its `.env`.
- **The worktree's stack is stopped.** `vendor/bin/sail up -d --wait` in `.worktrees/001-project-skeleton` brings it back with its data.

## Deploy notes

Nothing is deployed yet.

**Migrations:** 5.

- The first runs `CREATE EXTENSION IF NOT EXISTS postgis` and `pg_trgm`.
- `pg_trgm` is a trusted extension, so the database owner can create it; `postgis` is not, so it needs a superuser.
- In production, either migrate as a superuser, or have `postgis` created once by one, after which the migration skips it.

**Services:**

- PostgreSQL 18 with PostGIS 3.7 (`postgis/postgis:18-3.7`).
- Redis, which `.env.example` now uses for the cache and the queues.

**Packages:**

- Runtime: `laravel/framework` 13.43.0, `laravel/sanctum` 4.5.0, `laravel/tinker` 3.2.0.
- Development only: `pestphp/pest` 5.3.7 (PHPUnit 13.4.7), `pestphp/pest-plugin-laravel` 5.0.1, `larastan/larastan` 3.15.0 (PHPStan 2.3.7), `rector/rector` 2.8.5, `driftingly/rector-laravel` 2.7.3, `laravel/pint` 1.32.3, `laravel/boost` 2.10.4, `laravel/sail` 1.71.0, `laravel/pail` 1.3.0, `laravel/pao` 1.1.6.

## Update (2026-11-24)

- `DECISIONS.md` now matches #2 byte for byte: entries 1–8 from the owner's doc changes (#2), then this task's 9–18.
    - Before this, the two PRs conflicted at the end of the file in either merge order: both rewrote the end of the same list.
    - Checked with `git merge-tree` on the pushed branches, in both orders.
- The owner's steps 1 and 2 above are updated to match: merge #2 first.
