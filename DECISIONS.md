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
18. **Kept as Laravel ships them, for later steps:** `User` stays in `App\Models` until sign-in (step 1.4) moves it to the Identity domain (it moved earlier, in step 1.3a: entry 35), and the frontend skeleton (`package.json`, `vite.config.js`, the welcome page) stays for Filament's theme (step 1.5).

## Step 1.2 (task 002)

19. **The PostGIS image running emulated on Apple silicon is accepted** (owner's decision, entry 10).
20. **`composer.json` keeps `"php": "^8.5"`** (owner's decision, entry 14).
21. **Step 1.4 may edit task 001's `users` migration** instead of adding a second one, because nothing has been deployed (owner's decision). From the first deploy on, "never edit a merged migration" applies with no exception.
22. **CI runs on pushes to `main` and on pull requests only**, as in SellBubble (owner's decision): a branch with a pull request is checked once per push, not twice.
23. **Search matches from a trigram similarity of 0.25** (accepted by the owner), below pg_trgm's own 0.3 (`config('awez.search.similarity_threshold')`). Measured after `ArabicNormalizer` on PostgreSQL 18:
    - typos score from 0.27 (`مسشتفى` for `مستشفى`, two letters swapped) up to 1.0;
    - unrelated words score at most 0.09 (`مطعم` and `مستشفى`).
    
    At 0.3 the common swapped-letters typo would be missed. Search code must compare with this value (or set `pg_trgm.similarity_threshold`), not rely on the `%` operator's default.
24. **The API's middleware is global and acts on `/api/*` by path:** `AssignRequestId` (every request), `ForceJsonResponse` and `SetLocaleFromAcceptLanguage`, all three prepended in `bootstrap/app.php`. As route-group middleware, a 404 for an unknown route and a 405 would be thrown before the group runs, and would answer without the client's language.
25. **Other HTTP errors keep their status** (405, 503 in maintenance, ...), with the status's name as the code (`method_not_allowed`, `service_unavailable`) and a generic translated message. `server_error` is for 500 only. Headers such as `Allow` and `Retry-After` are kept.
26. **API rate limits: 60 requests a minute per signed-in user, 300 per IP address for guests** (accepted by the owner) (`config/awez.php`, `AWEZ_API_RATE_LIMIT_PER_USER` and `_PER_IP`).
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
32. **Validation messages come in Arabic from `laravel-lang/lang`** (owner's decision): a development dependency (15.39), whose Arabic files are published into `lang/ar` (`auth`, `pagination`, `passwords`, `validation`, and `ar.json`) and committed, so production needs no package. `sail artisan lang:update` refreshes them. Field names stay as sent (`حقل name مطلوب.`) until the API translates attribute names.
33. **Mutation testing runs with `composer test:mutate`** (pcov, minimum score 100), on the classes that declare `mutates()` in their tests: `MoneyCast` for now. It is not part of `composer check` or CI; run it when money code changes.

## Step 1.3a (task 003)

34. **Step 1.3 is split in two** (the brief): 1.3a, geography and content (task 003), and 1.3b, interaction and business (task 004, planned).
35. **`User` lives in `App\Domains\Identity\Models`.** Domain models are linked to their factories with `#[UseFactory]`, because Laravel's naming convention only finds factories for `App\Models`, and to their policies with `#[UsePolicy]`. Factories stay in `database/factories`.
36. **The morph map is enforced, with short names:** `user`, `property`, `provider`, `place`, `used_item`, `request`, `request_reply`.
    - `user` is there because Sanctum's `tokenable` is a morph too: tokens now store `user`, not a class name. A token created before this change would no longer resolve; nothing is deployed.
    - A model missing from the map throws when it is stored in a morph column.
37. **Points.**
    - `PointCast` writes `ST_SetSRID(ST_MakePoint(lng, lat), 4326)::geography` as a query expression. Laravel inlines expressions without bindings, so this SQL is built from the `Point`'s two range-checked floats, never from text. They are written with `%.15F`: plain decimals in every locale, exact for every coordinate in Egypt.
    - It reads the EWKB hex PostgreSQL returns, and compares by coordinates, so reading a model or setting the same place again is not a change.
    - `HasLocation`'s scopes use bindings and name `location` unqualified: Larastan requires literal SQL, and a join with another `location` column fails as ambiguous rather than silently.
38. **Search compares the term with words, not with the whole text: `strict_word_similarity()`.**
    - `similarity()` of a term with a whole `search_text` falls to 0.08–0.15 even for an exact word, so search cannot use it.
    - `strict_word_similarity()` compares the term with the best-matching whole words. It reproduces task 002's word-to-word measurements (0.273 for the swapped-letter typo, at most 0.091 for unrelated words), so the 0.25 threshold keeps its meaning.
    - `word_similarity()` is looser: 0.2 for an unrelated word.
39. **Review: two findings about search, for step 2.6.** Measured on PostgreSQL 18 with 20,030 properties, in a transaction that was rolled back.
    - *Short words.* At 0.25, a term that begins like a two-letter word, which Arabic text is full of, matches every text that contains that word: `منزل` scores 0.333 against `من`, `فيللا` 0.286 against `في`, `علاج` 0.286 against `علي` (normalized `على`), `لابتوب` 0.25 against `لا`. Options: leave words under three letters out of `search_text` and out of search terms, keeping the threshold; or raise the threshold to 0.34, which misses the swapped-letter typo.
    - *The index.* The GIN index serves `search_text %>> :term`, the indexed column on the left; `:term <<% search_text` is not served. It answered in 187 ms against 2,932 ms for a sequential scan, yet the planner chose the scan, because pg_trgm prices the operator's function at cost 1 per row. After `ALTER FUNCTION strict_word_similarity_commutator_op(text, text) COST 100` the planner chose the index by itself (177 ms). That statement needs the extension's owner, which a managed database may not grant.
40. **Opening hours are Cairo wall-clock times**, the one exception to "time is UTC" (invariant 4): a shop opens at 09:00 whatever the offset is that day.
    - `OpeningHoursCalculator` reads the moment in `awez.display_timezone`, summer time included, and compares wall-clock seconds. Those differ from seconds elapsed since midnight on the two days a year Egypt changes its clocks, which it does at midnight.
    - An interval is open from its opening time up to, not including, its closing time. A closing time earlier than the opening time is on the next day; the same time for both means 24 hours. Several rows on one weekday are split shifts, and a weekday with no rows is closed.
41. **Review: a resident's content is deleted with their account** (`user_id` with `cascadeOnDelete`, as the brief says; account deletion comes in step 1.4). A brokered property, its agreement, and later its leads may need to outlive the account, because the platform's commission rests on them. Options at step 1.4: refuse to delete an account while it has a brokered listing, or keep those rows and blank their owner.
42. **Reference data is never deleted from under content.** Properties, providers, places, used items and requests reference cities, districts, trades and place categories with `restrictOnDelete`; admins deactivate a row (`is_active`) instead.
    - A city's districts go with the city (the brief).
    - A place category with subcategories cannot be deleted.
    - A place an admin added outlives the account that claimed it (`nullOnDelete`).
43. **Review: the districts list is incomplete.** The seeder has the seven the brief names (the first, second, fourth, seventh, eighth and eleventh residential areas, and the industrial zone), without centres. The owner's full list, with a centre for each, goes in the seeder; admins can add districts in the panel from step 1.5.
44. **Review: icons are Lucide names** (`wrench`, `pill`, ...), each checked against Lucide's list. Lucide is the stroke set closest to the admin panel's Heroicons. The mobile app ships Lucide, or maps these names to its own set.
45. **Review: a place's phone and WhatsApp numbers go through `PhoneNumberCast`**, as the brief says, so only Egyptian mobile numbers are accepted. Many pharmacies and shops publish a landline (`048` in Menoufia), which is refused until `PhoneNumber` learns landlines.
46. **Who may see what**, at the policy level until the endpoints of phase 2 add their own tests:
    - moderated content (properties, providers, places, used items): published rows to everyone, and the owner's own rows in any status;
    - requests: open ones to everyone, unless `expires_at` has passed, even before a job marks them expired; closed and expired ones to their owner only;
    - **Review:** a reply is seen only by its author and by the request's owner, who cannot change it, so competitors do not see each other's offers;
    - a report is seen only by the resident who filed it, and a place nobody has claimed is changed only by admins.
47. **Money columns.** A required amount has a required `currency`; an optional amount has an optional `currency`, with a `CHECK` that an amount always has one. Two amounts on one row share `currency` (`price_from` and `price_to`), and `price_to` is not below `price_from`.
48. **Constraints beyond the brief's**, each a rule the data must never break:
    - a sale has no rent period (the brief's `CHECK` works both ways);
    - an agreement for a fixed amount has a currency, and ends after it is signed;
    - an offer ends after it starts;
    - a request has a trade only when it asks for a craftsman, and a place category only when it asks for a place.
49. **`provider_trades` has no id and no timestamps:** it is a pivot keyed by its pair, as the brief says, the one exception to "a UUID key and timestamps".
50. **Demo content.**
    - 30 residents own the listings, places, items, requests and reports. The 40 craftsmen have accounts of their own, because a profile is one per account: 70 users in all.
    - `DemoContentSeeder` throws outside `local` and `testing`, and `DatabaseSeeder` calls it only there.
    - It adds content on every run, so start from `migrate:fresh --seed`; the reference data is idempotent.
    - The demo agreement's commission rate is a placeholder (OPEN_QUESTIONS 1).
    - Seeders run with model events on (Laravel's `WithoutModelEvents` is removed), because `HasSearchText` fills `search_text` when a row is saved.
51. **Factories make content pending review**, as new content is. The states `published()`, `rejected()`, `paused()` and `inStatus()` set the moderation columns together.
52. **Larastan reads `casts()`** (`parseModelCastsMethod: true`), so attributes have their cast types: enums, `Money`, `Point`, dates. Each moderated model lists the moderation casts in its own `casts()`; a trait merging them at runtime is invisible to Larastan.
53. **"Newest first" can order by `id`:** UUID v7 keys are time-ordered, so lists need no separate `created_at` index.
