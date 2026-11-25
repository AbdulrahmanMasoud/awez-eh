# Build plan

The order in which the Awez Eh backend and admin panel are built. Each step ends with something that runs and is verified before the next step starts. Every step follows the definition of done in `CLAUDE.md`; the **Verify** lines below are the step-specific checks on top of it.

Tick a box only after its verification has been run and passed. A step without acceptance criteria gets them written here before its work starts.

---

## Phase 0 — Outside the code (start now, it takes time)

- [ ] Firebase project with Cloud Messaging; a service account for the backend
- [ ] Google Sign-In (Google Cloud OAuth client) and Sign in with Apple (Apple Developer account), both free
- [ ] Before launch: the WhatsApp Business number used to confirm phone numbers (ADR 0003)
- [ ] Cloudflare account with an R2 bucket
- [ ] A VPS for production
- [ ] A lawyer's review of the brokerage agreement and the commission terms
- [ ] The first data: 100 places and craftsmen, 30 properties, Sadat City's districts

## Phase 1 — Foundation

- [x] **1.1 Project skeleton and quality gate.** (task 001)
    - Acceptance:
        - A fresh Laravel 13 application (latest stable, no starter kit: this is an API and a Filament panel) at the repository root, keeping the existing `docs/`, `CLAUDE.md`, `DECISIONS.md` and `PROGRESS.md`.
        - Laravel Sail with PHP 8.5, PostgreSQL 18 with PostGIS (an official `postgis/postgis` image for PostgreSQL 18), and Redis. Every host port comes from `.env`, so the stack runs beside other local projects.
        - A first migration enables the `postgis` and `pg_trgm` extensions. Feature tests prove: the database is PostgreSQL 18 on the testing database, both extensions are available, Redis answers, un-faked outbound HTTP is refused, and `GET /up` returns 200.
        - Primary keys are UUID v7 (`HasUuids`); the default `users` migration is changed accordingly before anything is deployed.
        - Pint (Laravel preset + `declare_strict_types`), Rector (with `driftingly/rector-laravel`), Larastan at `level: max` with no baseline, Pest 5 with Unit, Feature and Arch suites.
        - Architecture tests for every rule in `CLAUDE.md` › Architecture tests that can already be checked.
        - Composer scripts: `setup`, `dev`, `check`, `test`, `fmt`.
        - Laravel Boost installed for development (MCP server and skills only, not its guidelines block in `CLAUDE.md`).
        - GitHub Actions runs each part of `composer check` as its own step on every push and pull request, with PostgreSQL + PostGIS and Redis services.
        - `README.md` explains how to start the project with Sail.
    - Verify: fresh clone → `sail up -d` → `sail composer setup` → `GET /up` is 200; `sail composer check` green; `migrate:fresh --seed`, rollback and migrate again succeed; a throwaway violating class fails the arch tests.
- [x] **1.2 Core support.** (task 002)
    - Acceptance:
        - `AppServiceProvider`: strict models outside production, `CarbonImmutable` dates, and in production destructive database commands prohibited and HTTPS forced. `app.timezone` stays UTC; `awez.display_timezone` is `Africa/Cairo` for presentation.
        - `App\Exceptions\DomainException` (error code, translation key, HTTP status, private context). Every response under `/api/*` uses one JSON error shape `{message, code, errors}`: domain errors with their own code and status (not reported), `validation_failed` 422, `unauthenticated` 401, `forbidden` 403, `not_found` 404 (never naming the model or id), `too_many_requests` 429, and `server_error` 500 with no trace outside `local`. Messages in `lang/ar` and `lang/en`.
        - `MoneyCast` on `brick/money`: `bigint` minor units plus a currency column (EGP by default); it rejects anything but `Money`, never goes through a float, and refuses to change a row's currency silently. Mutation tested.
        - `PhoneNumber` for Egyptian mobiles (`010`, `011`, `012`, `015`), accepting the common ways of typing them, Arabic-Indic digits included; `e164()`, `national()`, `mask()`, a masked string form, and `PhoneNumberCast` storing E.164.
        - `ArabicNormalizer::normalize()` (alef forms, taa marbuta, alef maqsura, hamza on waw and yeh, diacritics, tatweel, Eastern digits, whitespace, Latin case), and a trigram similarity threshold for search, measured on PostgreSQL and recorded in `DECISIONS.md`.
        - A `request_id` in Laravel `Context` for every request (a safe incoming `X-Request-Id` or a new UUID), echoed in the response; a `json` log channel, one line per entry with the context; phone numbers, tokens and codes never logged.
        - Arabic as the default locale; `Accept-Language` (`ar` or `en`, else `ar`) sets the API's locale and `Content-Language`. One `api/v1` route group with a public `GET /api/v1/ping` resource, JSON responses whatever the client sends as `Accept`, and a named `api` rate limiter (per user, else per IP) configured in `config/awez.php`.
        - Architecture tests: controllers under `App\Http\Controllers\Api` return API Resources (or `204` responses); Form Requests are final and extend `FormRequest`; `App\Support` does not depend on `App\Domains`, `App\Http` or `App\Filament`.
        - CI on pushes to `main` and on pull requests only.
    - Verify: `sail composer check` green with the test count and `MoneyCast`'s mutation score reported; `curl -i localhost:8020/api/v1/ping` in Arabic and with `Accept-Language: en`; one real request per error type (through a throwaway route, then removed); a JSON log line carrying the `request_id`; a throwaway violating each new arch rule fails.
- [x] **1.3a Data model: geography and content.** (task 003)
    - Acceptance:
        - Domains `Geography`, `RealEstate`, `Services`, `Places`, `Marketplace`, `Requests` and `Moderation` under `app/Domains`, and `User` moved to `App\Domains\Identity\Models`. Factories stay in `database/factories`, linked with `#[UseFactory]`.
        - `App\Support\Geo\Point` (latitude and longitude with range checks), `PointCast` for `geography(Point, 4326)` columns (written as `ST_SetSRID(ST_MakePoint(lng, lat), 4326)::geography`, read from EWKB hex), the `HasLocation` scopes `withinMeters()` and `orderByDistance()` in SQL, and `HasSearchText`, which fills `search_text` with the normalized searchable attributes on saving.
        - `ContentStatus` (`draft`, `pending_review`, `published`, `paused`, `sold`, `rented`, `rejected`, `archived`; only `published` is public). Moderated tables (properties, providers, places, used items) start as `pending_review` and carry `rejection_reason`, `reviewed_by`, `reviewed_at`, `published_at` and an index on `(status, published_at)`, with a `published()` scope.
        - Tables, with UUID v7 keys, an index on every foreign key and every filter or sort column, GiST on geography, GIN `gin_trgm_ops` on `search_text`, and enums stored as strings with a `CHECK`: `cities`, `districts`, `properties`, `property_agreements`, `trades`, `providers`, `provider_trades`, `provider_prices`, `place_categories`, `places`, `place_items`, `place_offers`, `opening_hours`, `used_items`, `requests`, `request_replies`, `reports`. Money through `MoneyCast`; reference data with `name_ar`, `name_en` and a `name` that follows the locale; the morph map enforced with short names.
        - `OpeningHoursCalculator` (`Place::isOpenAt()`): Cairo wall-clock hours, across midnight, split shifts, closed days, closed at the closing minute.
        - Models with relationships both ways, and policies (`view`, `update`, `delete`) for every user-owned model: a published row or the owner's own; changes by the owner only.
        - Seeders: reference data everywhere (Sadat City, its districts, trades, place categories), idempotent; demo content in `local` and `testing` only.
        - Architecture tests: models live only in `App\Domains\*\Models`, are final, use `HasUuids` and declare their fillable attributes; every model with a `user_id` has a policy.
    - Verify: `sail composer check` green; on `testing`, `migrate:fresh --seed` (timed, under a minute), `migrate:rollback --step=<this step's migrations>` and `migrate` again; `\d properties`; an `EXPLAIN` of "published properties within 2 km, nearest first" using the GiST index and of a trigram search using the GIN index; row counts per table after seeding; ownership proven at the policy level for every user-owned table.
- [x] **1.3b Data model: interaction and business.** (task 004)
    - Acceptance:
        - Domains `Conversations`, `Bookings`, `Reviews`, `Favorites`, `Alerts`, `Commissions`, `Promotions` and `CityGuide`, following step 1.3a's patterns: UUID v7 keys, an index on every foreign key and filter or sort column, enums as strings with a `CHECK`, `MoneyCast`, `PointCast`, `#[UseFactory]`, `#[UsePolicy]`, the enforced morph map with a short name for every new morphable model.
        - Tables: `conversations`, `messages`, `bookings`, `booking_events`, `reviews`, `favorites`, `search_alerts`, Laravel's `notifications` (UUID morphs), `leads`, `commission_entries`, `featured_packages`, `featured_placements`, `guide_entries`, `duty_pharmacies`, `news_items`.
        - Append-only tables (`leads`, `booking_events`, `commission_entries`): `App\Support\Database\AppendOnly::enable()` in their migrations adds a trigger that refuses `UPDATE` and `DELETE`, and their models refuse `save()` on an existing row and `delete()`. Their parents use `restrictOnDelete`.
        - A brokered conversation or viewing has no counterpart (`counterpart_id` null): the platform answers, and the owner never appears.
        - `BookingStateMachine` (pure): the statuses, the transitions allowed between them, and who may make each. `CommissionBalance` (pure): expected, invoiced, collected and outstanding from a property's entries. Both mutation tested.
        - Policies: conversations and messages for their two participants only, bookings for the resident and the counterpart, reviews for everyone unless hidden, favorites, search alerts and featured placements for their owner, leads for nobody through the API. The arch rule covers `resident_id` as well as `user_id`.
        - Seeders: Sadat City's emergency numbers everywhere; demo conversations, bookings in every status with their events, reviews, favorites, alerts, leads (one voided), commission entries, featured packages and placements, duty pharmacies for 14 days and news, in `local` and `testing` only.
    - Verify: `sail composer check` green, with the mutation scores of `BookingStateMachine` and `CommissionBalance`; on `testing`, `migrate:fresh --seed` (timed), `migrate:rollback --step=<this step's migrations>` and `migrate` again; `\d leads` with its trigger; an `UPDATE` and a `DELETE` on `leads` refused in psql; row counts per new table and the demo bookings by status; ownership proven at the policy level for every table with an owner.
- [x] **1.4 Sign-in.** (task 005)
    - Acceptance:
        - Packages: `firebase/php-jwt` (ID tokens against the providers' JWKS) and `knuckleswtf/scribe` (development: API docs at `/docs` in `local` only). No Socialite.
        - `users` (task 001's migration edited, DECISIONS 21): `email` and `phone` nullable and unique where not null, `phone` a confirmed mobile only (`PhoneNumberCast`) with `phone_verified_at`, `password` null (admins only), `avatar_url`, `locale` (`ar`, `en`), `district_id` (`restrictOnDelete`), `status` (`active`, `suspended`, `deleted`) with `suspended_at` and `deleted_at`, `last_seen_at`. No role column: `isAdvertiser()` and `isProvider()` are derived.
        - Identity tables: `social_accounts` (unique `(provider, subject)`, one per provider per user), `phone_verifications` (the code's hash only, attempts, expiry, driver, IP; indexed for the rate limits), `device_tokens` (one per access token, which takes it with it).
        - `IdentityTokenVerifier` with Google and Apple drivers: signature against the provider's JWKS (cached for its `max-age`, an hour without one; an unknown `kid` refreshes once), `iss`, `aud` among the configured client ids, `exp` and `iat` with a small leeway, and for Apple the `nonce` claim equal to the SHA-256 of the app's nonce. Every failure is `invalid_identity_token` (401). A fake driver (`AWEZ_SIGN_IN_FAKE=true`, `fake:<email>`) is refused at boot outside `local` and `testing`.
        - Endpoints under `/api/v1`, each with a Form Request, API Resources and the one error shape: `POST auth/google`, `POST auth/apple` (find by provider and subject, else link to an active user with the same verified email, else create; 201 for a new user, 200 otherwise; a Sanctum token per device name, replaced on signing in again; `needs_phone`), `GET me`, `PATCH me`, `POST me/phone`, `POST me/phone/confirm`, `PUT me/device-token`, `POST auth/sign-out`, `DELETE me`. `GET ping` is removed. A suspended account gets `account_suspended` (403) everywhere; `last_seen_at` is written at most every five minutes.
        - `PhoneVerifier` with a `log` driver (refused at boot outside `local` and `testing`, writing the code only to its own `phone_verification` channel) and a challenge shaped for step 4.3b's WhatsApp driver. Six digits from `random_int`, stored as a hash, 10 minutes, 5 attempts, a new start replaces an open one. Rate limits per number (3 in 10 minutes, 10 a day), per user (5 an hour) and per IP (20 an hour): `too_many_attempts` (429, `Retry-After`). `verification_code_invalid` (422, attempts left), `verification_code_expired`, `phone_taken` (409, at start and at confirmation).
        - `DeleteAccount`: tokens, device tokens, social accounts and phone verifications go; each domain's own Action archives or deletes its rows (brokered properties archived with their agreements and leads, other content deleted unless something points at it, the provider profile archived, places released, open requests deleted or closed when they have replies, favorites, alerts and open reports deleted); the row stays with its personal data blanked and `status` `deleted`. Signing in again with the same Google account creates a new user.
        - Ownership at the API level: `me` always answers for the token's user, and A's token cannot change B's phone, device token or account. Arch: `App\Domains\Identity\Contracts` holds interfaces only, and only `App\Domains\Identity` uses `Firebase\JWT`.
        - Scribe documents every endpoint with Arabic examples, its error codes and `Accept-Language`.
    - Verify: `sail composer check` green with the test counts; on `testing`, `migrate:fresh --seed`, `migrate:rollback` of the step's migrations (the edited `users` one included) and `migrate` again; a real run on `localhost:8020` with the fake driver: a new and then the same "Google" identity, `GET me` with `needs_phone`, a phone confirmation started and its code read from the `phone_verification` log, a wrong code, the right one, `phone_taken` from a second account, the rate limit reached, a device token saved, sign-out (the token then answers 401), and an account deleted and anonymized (its row from psql); `/docs` generated.
- [x] **1.5 Admin panel foundation.** (task 006)
    - Acceptance:
        - Packages: Filament (the latest major supporting Laravel 13), `spatie/laravel-permission`, Filament Shield (its release for that major) and `spatie/laravel-activitylog`, with exact versions reported.
        - One panel, `admin`, at `/admin`, as `docs/design/ADMIN_PANEL.md` describes: Arabic and RTL by default, English from the user menu (stored in `users.locale`), a custom theme built with Vite inside Sail (brand tokens, Readex Pro), dark mode off, a collapsible sidebar, the navigation groups in the design's order (empty groups hidden), database notifications on. CI builds the theme.
        - Global search over users (name, email, or a phone number typed any way `PhoneNumber` reads it) and the reference data; results show masked numbers.
        - Who may enter: a user with an admin role, a password and `status` `active` (`canAccessPanel()`); residents get 403. Two-factor authentication through Filament's app authentication with recovery codes is required, and an admin without it is sent to set it up first. Sign-in is rate limited (Filament's limit, recorded); a suspended admin is signed out on the next request.
        - `awez:create-admin` (name, email, hidden confirmed password of at least 12 characters under `Password::defaults()`, role) creates or promotes a user and prints nothing secret. No admin with a known password is seeded, except the demo seeder's `admin@awez.test` in `local` and `testing`.
        - Roles `super_admin`, `moderator`, `data_entry` and `sales`, seeded everywhere and idempotent; Shield's permissions for this step's resources as the brief assigns them. Shield's checks live in the existing policies beside the resident rules, which keep passing unchanged.
        - Activity log on cities, districts, trades, place categories and users (status and roles only, never personal data). Suspending, reactivating, changing roles and revealing a number write an entry with the admin, the reason and before and after. A read-only activity log page for `super_admin`, filterable by admin, action and date, in Cairo time.
        - Resources: cities (no delete, `is_active`), districts (their own list and under their city, reorderable), trades and place categories (reorder, activate, Lucide icon select), and users (list with masked phones and filters; view page with providers, devices and content counts; suspend and reactivate through `SuspendUser` and `ReactivateUser` with a required reason, revoking every token; roles for `super_admin` only, never removing the last one; no create, personal-data edit or delete). Labels in `lang/ar` and `lang/en`; tables sort and filter on indexed columns only.
        - Dashboard: the design's header and period filter (today, week, month), with the widgets whose data exists: pending review (with the oldest one's age), new users (with the share that confirmed a phone), published listings by kind.
        - Tests: access for guests, residents, admins without 2FA, suspended admins and each role's navigation; every resource per role; suspend and reactivate with their log entries and revoked tokens; the last `super_admin`; search by a spaced phone number; the dashboard per period; `awez:create-admin`. Arch: Filament classes live in `App\Filament`, and `App\Domains` does not use them.
    - Verify: `sail composer check` green with the test counts, and the theme builds; on `testing`, `migrate:fresh --seed`, `migrate:rollback` of the step's migrations and `migrate` again; a real run on `localhost:8020`: `awez:create-admin`, signing in at `/admin`, setting up 2FA, and opening each resource, with screenshots of the dashboard and the users list in Arabic, or each page's status for an admin and a resident.

## Phase 2 — The first version (what residents need on launch day)

- [ ] **2.1 Media.** Image upload to R2 with two sizes through `spatie/laravel-medialibrary`.
- [ ] **2.2 Real estate API.** Listing, filters, sort, detail, create and edit by owner, moderation in Filament, brokered listings that hide the owner's number.
- [ ] **2.3 Services API.** Providers by trade, rating and availability; profile with prices and work photos; verification in Filament.
- [ ] **2.4 Places API.** Restaurants, shops, pharmacies; items, offers, opening hours, "open now".
- [ ] **2.5 Marketplace API.** Second-hand items by category, negotiable flag.
- [ ] **2.6 Search.** Scout on the database driver across all areas, Arabic normalization, typo tolerance, "near me". Words under three letters are left out of `search_text` and of search terms, and a migration prices the trigram operator's function at its real cost (DECISIONS 39).
- [ ] **2.7 Requests.** Create a request, matching advertisers see it, replies, views count.
- [ ] **2.8 Chat.** Conversations and messages through the API (polling first), leads recorded for brokered listings.
- [ ] **2.9 Favorites, reports and blocking.**

## Phase 3 — Interaction and trust

- [ ] **3.1 Bookings** with their state machine and events timeline; viewings of brokered properties create leads. Rescheduling is not a status: it edits `scheduled_at` and records its own booking event (DECISIONS 60).
- [ ] **3.2 Reviews** after a finished booking only; badges (verified, quick reply, jobs done).
- [ ] **3.3 Urgent craftsman** ("in 30 minutes"): dispatch to the nearest three available, offers with arrival time, acceptance.
- [ ] **3.4 Notifications** through FCM and the database; search alerts.
- [ ] **3.5 City guide** content in Filament and its API.
- [ ] **3.6 Real-time chat and booking status** with Reverb.

## Phase 4 — Launch

- [ ] **4.1 Advertiser statistics** (views, calls, messages) and featured listings.
- [ ] **4.2 Craftsman mode** (availability, accepting urgent jobs).
- [ ] **4.3 Commissions** in Filament: agreements, leads per listing, commission entries.
- [ ] **4.3b Phone confirmation by WhatsApp**: the resident sends a pre-filled code to our WhatsApp number; the webhook confirms the number (ADR 0003).
- [ ] **4.4 Production**: Dockerfile, deployment, backups, error tracking (Sentry), API docs published.

## Phase 5 — Growth (after launch)

Daily market prices, water and power outages, city jobs, student and worker housing, share links with previews (public listing pages), ready-made cards for Facebook groups, city guide points, referrals, ask the city and lost and found, more cities, delivery.

## Phase 6 — Scale (when measured)

Horizon when queues need watching; Meilisearch when database search is too slow or too weak; Octane when the server is the bottleneck; a separate database server and read replicas.
