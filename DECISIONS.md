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

## Step 1.2 (task 002)

19. **The PostGIS image running emulated on Apple silicon is accepted** (owner's decision, entry 10).
20. **`composer.json` keeps `"php": "^8.5"`** (owner's decision, entry 14).
21. **Step 1.4 may edit task 001's `users` migration** instead of adding a second one, because nothing has been deployed (owner's decision). From the first deploy on, "never edit a merged migration" applies with no exception.
22. **CI runs on pushes to `main` and on pull requests only**, as in SellBubble (owner's decision): a branch with a pull request is checked once per push, not twice.
23. **Search matches from a trigram similarity of 0.25**, below pg_trgm's own 0.3 (`config('awez.search.similarity_threshold')`). Measured after `ArabicNormalizer` on PostgreSQL 18:
    - typos score from 0.27 (`مسشتفى` for `مستشفى`, two letters swapped) up to 1.0;
    - unrelated words score at most 0.09 (`مطعم` and `مستشفى`).
    
    At 0.3 the common swapped-letters typo would be missed. Search code must compare with this value (or set `pg_trgm.similarity_threshold`), not rely on the `%` operator's default.
24. **The API's middleware is global and acts on `/api/*` by path:** `AssignRequestId` (every request), `ForceJsonResponse` and `SetLocaleFromAcceptLanguage`, all three prepended in `bootstrap/app.php`. As route-group middleware, a 404 for an unknown route and a 405 would be thrown before the group runs, and would answer without the client's language.
25. **Other HTTP errors keep their status** (405, 503 in maintenance, ...), with the status's name as the code (`method_not_allowed`, `service_unavailable`) and a generic translated message. `server_error` is for 500 only. Headers such as `Allow` and `Retry-After` are kept.
26. **API rate limits: 60 requests a minute per signed-in user, 300 per IP address for guests** (`config/awez.php`, `AWEZ_API_RATE_LIMIT_PER_USER` and `_PER_IP`).
    - The guest limit is high because Egyptian mobile networks put many customers behind one address.
    - The limiter asks the `sanctum` guard, so a resident who sends a token is counted alone even on public routes.
    - Behind a proxy, `TrustProxies` must be configured for the IP to be the client's (step 4.4).
27. **`MoneyCast` refuses rather than guesses:**
    - only `Money` is accepted;
    - an amount with more decimals than its currency throws instead of rounding;
    - a row's currency changes only if the currency column is set first;
    - negative amounts are allowed, for refunds.
28. **`PhoneNumber` accepts exactly the forms in task 002's brief**, plus spaces, dashes, Eastern digits and invisible direction marks. Not accepted: `+20 0…` (a country code with the trunk zero), parentheses and dots. It masks the national form (`010*****678`), and `PhoneNumberCast` takes only `PhoneNumber` objects. Its errors carry no context, because the rejected input may be a real number.
29. **Logs:**
    - `LOG_STACK=single,json`: Boost reads `laravel.log`, and `laravel.json` has one JSON object per line.
    - A `RedactLogs` tap on the `single`, `daily`, `stderr` and `json` channels replaces values under secret-looking keys (`password`, `token`, `secret`, `authorization`, `code`, `otp`, ...) with `[redacted]`.
    - It also masks Egyptian mobile numbers written as plain text in messages and context.
30. **API JSON is UTF-8:** Arabic is not sent as `\u` escapes, which take three times the bytes.
31. **Tests send no `Accept-Language` unless they set one** (`tests/TestCase.php`), because Symfony's test requests otherwise claim `en-us`.
32. **Validation messages for each field are English for now**, even in Arabic: Laravel ships no Arabic `validation.php`. The `message` and `code` are translated. **Review:** a translation package or our own `lang/ar/validation.php` (docs/OPEN_QUESTIONS.md 3).
33. **Mutation testing runs with `composer test:mutate`** (pcov, minimum score 100), on the classes that declare `mutates()` in their tests: `MoneyCast` for now. It is not part of `composer check` or CI; run it when money code changes.
