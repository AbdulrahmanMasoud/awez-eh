# Decisions log

Decisions taken while executing `docs/BUILD_PLAN.md`: what was chosen and why, so it can be reviewed and reversed. Architecture decisions also get an ADR in `docs/adr/`. Entries marked **Review** need the owner's eyes.

## Before step 1.1

1. **Laravel, PostgreSQL 18 with PostGIS and pg_trgm, Redis, Filament** (owner's choice, ADR 0001). Supabase, Node.js and Go were weighed.
2. **No tenants, no RLS** (ADR 0002): ownership by Policies.
3. **Sign-in with Google and Apple, Sanctum tokens** (ADR 0003).
4. **Push notifications through Firebase Cloud Messaging** (owner's choice), using `laravel-notification-channels/fcm`.
5. **Search through Scout on the database driver first**, Meilisearch later by configuration.
6. **Horizon, Meilisearch and Octane are not installed at first**; they join when measured need appears (BUILD_PLAN Phase 6).
7. **No paid SMS and no Google Maps at first** (owner's choice): sign-in with Google and Apple, phone numbers confirmed by a code the resident sends on WhatsApp, a `log` driver while building (ADR 0003); maps on OpenStreetMap through MapLibre and OpenFreeMap (ADR 0005).
8. **Same working agreement as SellBubble**: briefs in `docs/tasks`, reports in `docs/reports`, a worktree and a PR per task, the owner merges.

## Step 1.1 (task 001)

9. **UUID v7 primary keys from the first migration.** `users.id` is a `uuid` filled by `HasUuids` (time-ordered v7 in Laravel 13). `sessions.user_id` is a UUID foreign key that cascades on delete, and Sanctum's `personal_access_tokens.tokenable` uses `uuidMorphs`. Framework tables (`jobs`, `failed_jobs`, the token's own id) keep Laravel's integer ids.
10. **`postgis/postgis:18-3.7`** (PostgreSQL 18.7, PostGIS 3.7), the newest official tag for PostgreSQL 18. **Review:** it is published for amd64 only, so `compose.yaml` pins `platform: linux/amd64` and Apple silicon runs it emulated (the test suite still runs in about 3 s). A native arm64 image would mean a community image or building our own.
11. **The first migration owns the extensions.** Our init script replaces the image's own, which would load PostGIS, topology and the tiger geocoder into the dev database. It only creates `testing`, so the dev and testing databases start empty and identical, and the migration's `down()` works on both.
12. **Host ports 8020 (app), 5452 (PostgreSQL), 6399 (Redis)**, SellBubble's plus 20 for the database and Redis, read from `.env`. No Vite port: nothing is built for a frontend.
13. **Cache and queues on Redis** in `.env.example` (ARCHITECTURE §2); sessions stay on the database. Tests use the array cache and the sync queue.
14. **`composer.json` requires PHP `^8.5`**, the stack's version, which also points Rector's PHP sets at 8.5. **Review** if Laravel's `^8.3` floor is preferred.
15. **Laravel Boost's generated files are git-ignored** (`boost.json`, `.mcp.json`, `.claude/`, `AGENTS.md`), as in SellBubble. Each developer runs `sail artisan boost:install --skills --mcp`, never `--guidelines`, so `CLAUDE.md` stays ours.
16. **Parallel tests get one database per worker** (`testing_test_1`, ...), Laravel's default, each migrated by its worker. SellBubble shares one database only because of its row level security roles.
17. **`sail up -d --wait`** in the README: PostgreSQL's healthcheck goes over TCP, so it turns healthy only after the image's first-start initialisation, and `composer setup` never meets a database that is still starting.
18. **Kept as Laravel ships them, for later steps:** `User` stays in `App\Models` until sign-in (step 1.4) moves it to the Identity domain, and the frontend skeleton (`package.json`, `vite.config.js`, the welcome page) stays for Filament's theme (step 1.5).
