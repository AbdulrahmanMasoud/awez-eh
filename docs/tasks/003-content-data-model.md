# Task 003: Data model, part 1 (geography and content)

- **Branch:** `task/003-content-data-model`, in `.worktrees/003-content-data-model` (`docs/tasks/README.md`).
- **Depends on:** task 002 (merged as #3).
- **Build plan step:** 1.3, split in two by this task. Before coding, replace step 1.3 in `docs/BUILD_PLAN.md` with:
    - **1.3a Data model: geography and content** (this task), with **Acceptance** and **Verify** lines written from this brief;
    - **1.3b Data model: interaction and business** (task 004, `planned`): conversations, messages, bookings, booking events, reviews, favorites, search alerts, notifications, leads, commission entries, featured packages and placements, city guide.
    - Add task 004's row to the index as `planned`.

## Why

Step 1.3 covers about 40 tables, too many for one reviewable PR. This task builds the tables the first API steps (2.2 to 2.7) read and write: the city, the five kinds of content, requests and reports. The interaction tables (chat, bookings, leads, …) are designed in task 004, when their shapes are clearer.

Identity tables (`otp_codes`, `device_tokens`, new `users` columns) belong to step 1.4, and `media` to step 2.1 (spatie/laravel-medialibrary). Do not create them here.

## Housekeeping first

As `docs/tasks/README.md` says. Task 002 is already `done`.

## 1. Domain layout

- Create `app/Domains/<Domain>/{Enums,Models,Policies}` for Geography, RealEstate, Services, Places, Marketplace, Requests and Moderation, with the other folders of `CLAUDE.md` only when something goes in them.
- Move `User` to `app/Domains/Identity/Models/User.php` (and its factory reference, `config/auth.php`, Sanctum, tests). Nothing else in Identity yet.
- Factories stay in `database/factories`, one per model, linked with `#[UseFactory]` or `newFactory()`.
- An arch test: models live only under `App\Domains\*\Models`; every model is final, uses `HasUuids` and declares `$fillable` (or the `Fillable` attribute).

## 2. Shared building blocks (in `App\Support`, no domain code)

- **`App\Support\Geo\Point`** (final, readonly): `latitude`, `longitude` as floats with range checks (coordinates are not money, so floats are right here). No new package.
- **`App\Support\Casts\PointCast`**: stores a `Point` in a `geography(Point, 4326)` column and reads it back. Writing goes through `ST_SetSRID(ST_MakePoint(lng, lat), 4326)::geography` (an expression, never string-built SQL from input), reading parses what PostgreSQL returns (EWKB hex). Unit tests for parsing, feature tests for the round trip on PostgreSQL. Longitude first in SQL, latitude first in PHP: test that the order is right with a real Sadat City point (30.3811, 30.5266).
- **`App\Support\Geo\HasLocation`** (a model trait): scopes `withinMeters(Point $from, int $meters)` (`ST_DWithin`) and `orderByDistance(Point $from)` (`ST_Distance`), both in SQL (invariant 5). A feature test with three points at known distances.
- **`App\Support\Text\HasSearchText`** (a model trait): the model lists its searchable attributes; on `saving`, `search_text` is set to `ArabicNormalizer::normalize()` of them joined. A feature test that `صيدليّة` saved is found by `صيدليه` with the trigram threshold from `config('awez.search.similarity_threshold')`. Scout comes in step 2.6; this column is what it will index.

## 3. Shared enums and columns

- **`App\Domains\Moderation\Enums\ContentStatus`**: `Draft`, `PendingReview`, `Published`, `Paused`, `Sold`, `Rented`, `Rejected`, `Archived` (values snake case). One method, `isPublic()`, true for `Published` only (ARCHITECTURE §5).
- Every moderated table (properties, providers, places, used items) has: `status` (default `pending_review`, invariant 9), `rejection_reason` text null, `reviewed_by` uuid null FK `users` `nullOnDelete`, `reviewed_at`, `published_at`, and an index on `(status, published_at)`.
- A `published()` scope on those models. Transitions are Actions in later steps; nothing in this task changes a status except factories and seeders.
- Owned rows have `user_id` FK `users` `cascadeOnDelete` (account deletion in step 1.4 removes a resident's content; record this in `DECISIONS.md` as a **Review** item, since brokered properties may need to outlive an account).
- Money columns are a `bigint` amount and a `currency` column through `MoneyCast` (`price` + `currency`; two amounts on one row share `currency`).
- Text a resident writes is stored once, in the language they wrote it. Reference data an admin edits (cities, districts, trades, categories) has `name_ar` and `name_en`, and a `name` accessor that follows the app's locale.

## 4. Tables

Every table: UUID v7 key, timestamps, an index on every foreign key and on every column a list filters or sorts by, in the same migration. Geography columns get a GiST index; `search_text` gets a GIN `gin_trgm_ops` index. Enums are PHP backed enums cast on the model, stored as strings with a `CHECK` constraint listing the values.

**Geography**

- `cities`: `name_ar`, `name_en`, `slug` unique, `center` geography point, `is_active` bool.
- `districts`: `city_id` FK `cascadeOnDelete`, `name_ar`, `name_en`, `slug` (unique per city), `center` geography point null, `sort_order`.

**RealEstate**

- `properties`: `user_id`; `purpose` (`sale`, `rent`); `type` (`apartment`, `villa`, `duplex`, `house`, `land`, `shop`, `office`, `warehouse`, `factory`); `title`, `description`, `search_text`; `price` + `currency`; `rent_period` null (`monthly`, `yearly`), required when `purpose` is `rent` (a `CHECK`); `area_m2` int; `bedrooms`, `bathrooms`, `floor` smallint null; `furnished` bool; `finishing` null (`unfinished`, `semi_finished`, `finished`, `luxury`); `city_id`, `district_id`, `address_note` null, `location` point; `brokered` bool default false; the moderation columns; `views_count` int default 0. Indexes also on `(purpose, type)`, `district_id`, `price`.
- `property_agreements` (admin only, the written brokerage agreement): `property_id` FK `cascadeOnDelete`, `owner_name`, `owner_phone` (`PhoneNumberCast`), `commission_rate_basis_points` int null and `commission_fixed` + `currency` null (a `CHECK` that exactly one is set), `signed_on` date, `ends_on` date null, `notes` null, `created_by` FK `users` `nullOnDelete`. The rate itself is still an open question (OPEN_QUESTIONS 1); only the columns exist.

**Services**

- `trades`: `name_ar`, `name_en`, `slug` unique, `icon` (a name from the app's icon set), `sort_order`, `is_active`.
- `providers` (a craftsman's profile; one per user): `user_id` unique; `display_name`, `bio` null, `search_text`; `city_id`, `district_id`, `location` point, `service_radius_m` int; `years_experience` smallint null; `verification` (`unverified`, `pending`, `verified`, `rejected`) and `verified_at`; `is_available` bool default false (craftsman mode, 4.2); `rating_sum`, `rating_count`, `jobs_done` int default 0 (counters filled by reviews and bookings later; the average is computed, never stored as a float); the moderation columns.
- `provider_trades`: `provider_id`, `trade_id`, `is_primary` bool; primary key on the pair; at most one primary per provider (partial unique index).
- `provider_prices`: `provider_id`, `label`, `price_from` and `price_to` null + `currency`, `unit` (`visit`, `hour`, `job`, `square_meter`), `sort_order`.

**Places**

- `place_categories`: `parent_id` null (self FK), `name_ar`, `name_en`, `slug` unique, `icon`, `sort_order`, `is_active`.
- `places`: `user_id` null (a place an admin adds has no owner until it is claimed; `nullOnDelete`); `place_category_id`; `name`, `description` null, `search_text`; `phone` and `whatsapp` null (`PhoneNumberCast`; a place's public number, not a person's); `city_id`, `district_id`, `address_note` null, `location` point; `delivers` bool; the moderation columns.
- `place_items` (menu or products): `place_id`, `name`, `description` null, `price` + `currency` null, `is_available`, `sort_order`.
- `place_offers`: `place_id`, `title`, `description` null, `starts_at`, `ends_at` (UTC); index on `(place_id, ends_at)`.
- `opening_hours`: `place_id`, `weekday` smallint 0–6 (0 = Sunday), `opens_at`, `closes_at` (`time`). These are **Cairo wall-clock times**, the one exception to "time is UTC" because opening hours are local by nature: record it in `DECISIONS.md` and say it in a comment on the model. `closes_at` earlier than `opens_at` means the next day. Several rows per weekday are allowed (split shifts). Write `Place::isOpenAt(CarbonImmutable $moment)` in `App\Domains\Places\Support\OpeningHoursCalculator` (pure, unit tested: across midnight, split shifts, a closed day, the moment exactly at closing).

**Marketplace**

- `used_items`: `user_id`; `category` (`furniture`, `appliances`, `electronics`, `mobiles`, `vehicles`, `clothes`, `kids`, `books`, `other`); `title`, `description`, `search_text`; `price` + `currency`; `negotiable` bool; `condition` (`new`, `like_new`, `good`, `fair`); `city_id`, `district_id`, `location` point null; the moderation columns; `views_count`.

**Requests**

- `requests` ("what do you want"; not moderated before publishing, reports handle abuse): `user_id`; `area` (`real_estate`, `services`, `places`, `marketplace`, `other`); `trade_id` null, `place_category_id` null; `body`, `search_text`; `budget` + `currency` null; `city_id`, `district_id` null, `location` point null; `status` (`open`, `closed`, `expired`); `expires_at`; `replies_count`, `views_count`.
- `request_replies`: `request_id` `cascadeOnDelete`, `user_id`, `body`, `offered_price` + `currency` null.

**Moderation**

- `reports`: `user_id` (the reporter), `reportable` (`uuidMorphs`), `reason` (`fake`, `scam`, `spam`, `wrong_price`, `offensive`, `other`), `note` null, `status` (`open`, `resolved`, `dismissed`), `resolved_by` null, `resolved_at` null. One open report per reporter and item (partial unique index). The morph map is enforced (`Relation::enforceMorphMap`) with short names (`property`, `provider`, `place`, `used_item`, `request`, `request_reply`).

## 5. Models, relationships and policies

- Relationships both ways for every foreign key, typed with generics in PHPDoc.
- **Policies** for the user-owned models (`Property`, `Provider`, `UsedItem`, `ServiceRequest` (the model for `requests`; `Request` clashes with the HTTP class), `RequestReply`, `Report`, and `Place` when it has an owner): `view` (a published row, or the owner's own row in any status), `update` and `delete` (the owner only). No admin rules here: Filament uses its own (step 1.5).
- **Ownership proven** (invariant 1, DoD 6) at the policy level for every one of those tables: user A cannot view B's unpublished row, update it or delete it. The API-level proof comes with each endpoint in phase 2; say so in the report.
- `ArchitectureTest`: every model in `App\Domains` with a `user_id` has a policy.

## 6. Seeders

- **Reference data** (runs everywhere, idempotent with `updateOrCreate` on the slug):
    - Sadat City (`sadat-city`), center 30.3811, 30.5266.
    - Districts, the residential areas the city uses: المنطقة السكنية الأولى، الثانية، الرابعة، السابعة، الثامنة، الحادية عشرة، and المنطقة الصناعية. This list is incomplete: add a **Review** entry in `DECISIONS.md` asking the owner for the full list (admins can add districts in Filament from step 1.5). Centers stay null until the owner supplies them.
    - Trades: سباك، كهربائي، نجار، نقاش، محارة، سيراميك، ألوميتال، تكييف وتبريد، صيانة أجهزة، حداد، عامل نظافة، نقل عفش، مدرس خصوصي.
    - Place categories: مطاعم (مأكولات شعبية، مشويات، بيتزا، كافيهات)، صيدليات، سوبر ماركت، مخابز، ملابس، موبايلات، أدوات منزلية، مستلزمات بناء.
- **Demo content** (local and testing only, never in production, guarded by the environment): 30 properties (a third brokered, one with an agreement), 40 providers across trades, 40 places with items, hours and offers, 60 used items, 20 requests with replies, a few reports, owned by 30 demo residents. Statuses mixed, most published. Arabic titles and descriptions that read like real ones, locations spread inside the city (within about 4 km of the center).
- `migrate:fresh --seed` on `testing` must finish in under a minute; report the time.

## Out of scope

Endpoints, Actions, Form Requests and Resources (phase 2), Scout (2.6), media (2.1), identity tables and `users` changes (1.4), Filament (1.5), every table of task 004.

## Acceptance

- `sail composer check` green; test counts in the report.
- Migrations verified on `testing` as the definition of done says: `migrate:fresh --seed`, `migrate:rollback --step=<this task's migrations>`, `migrate` again.
- Real run, pasted in the report:
    - `\d properties` (psql) showing the columns, the `CHECK` constraints and the GiST and GIN indexes;
    - one `EXPLAIN` of a "properties within 2 km of a point, published, ordered by distance" query that uses the GiST index;
    - one trigram search on `search_text` that uses the GIN index;
    - row counts per table after seeding.
- Every new decision in `DECISIONS.md`; `ARCHITECTURE.md` §3 updated if a table changed name or moved.
