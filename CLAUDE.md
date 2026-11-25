# CLAUDE.md

Rules for AI coding assistants and humans working on Awez Eh. Read all of it before changing code. If a request conflicts with a rule here, stop and explain the conflict instead of breaking the rule.

## Project in one paragraph

Awez Eh ("What do you want?") is a city app for Egypt, starting with Sadat City: real estate for sale and rent (the platform takes a commission on deals it brings), craftsmen and home services, restaurants, shops and pharmacies, second-hand items, a city guide, and requests where a resident writes what they need and the right people reply. This repository is the backend: a versioned JSON API for the mobile app (Expo, built later in its own repository) and the Filament admin panel. Stack: PHP 8.5, Laravel 13, PostgreSQL 18 with PostGIS and pg_trgm, Redis, Sanctum, Filament, Laravel Scout (database driver first), Firebase Cloud Messaging, Cloudflare R2, Pest 5, Larastan, Pint, Rector. Local development runs in Laravel Sail. Design: `docs/ARCHITECTURE.md`. Build order: `docs/BUILD_PLAN.md`.

**Versions** follow the latest stable Laravel release and the defaults it ships (Sail runtime and service images). Ask before changing any version, up or down.

Laravel Boost is installed for development. Use its documentation search to confirm the exact API of the installed versions (Laravel, Sanctum, Filament, Pest, Scout) instead of relying on memory.

## Commands

Everything runs inside Sail (`sail` is `vendor/bin/sail`; ADR 0004). Host PHP is not used.

```bash
sail up -d                  # app (http://localhost:8020), PostgreSQL + PostGIS, Redis
sail composer dev           # queue worker and logs
sail composer check         # THE gate: pint --test, rector --dry-run, phpstan, pest (unit + feature + arch)
sail composer test          # pest unit + feature + arch, parallel
sail composer test:mutate   # mutation testing of money, commissions and the booking state machine (score 100), run when they change
sail composer fmt           # pint + rector (writes)
sail composer docs          # API docs (Scribe) at http://localhost:8020/docs, in local only
sail artisan migrate:fresh --seed
```

- Open questions waiting on the owner, and known issues not fixed: `docs/OPEN_QUESTIONS.md`.
- "start task NNN" and "continue task NNN" follow `docs/tasks/README.md` (a worktree per task, the brief, the PR and the report).

## Definition of done (every step, no exceptions)

A step is finished only when all of these are true. Report each one in the step summary.

1. **Scope written first.** The step and its acceptance criteria are in `docs/BUILD_PLAN.md` before coding starts.
2. **Tests first.** Failing tests that describe the behavior exist before the implementation: unit tests for pure logic, feature tests for HTTP, jobs and DB behavior, architecture tests for new rules.
3. **`composer check` is green.** No skipped tests, no Larastan baseline, no `@phpstan-ignore` without a written reason.
4. **Migrations verified** on the `testing` database: `migrate:fresh --seed` succeeds, `migrate:rollback --step=<n>` for the step's migrations succeeds, then migrate again. Never on the dev database, which holds the owner's demo data.
5. **Real run.** The scenario is executed for real at least once (an HTTP call to the API, an artisan command, or the admin panel) and the observed output matches the acceptance criteria.
6. **Ownership proven** for every new user-owned table (invariant 1).
7. **Docs updated:** this file if a rule changed, `ARCHITECTURE.md` if the design changed, `docs/BUILD_PLAN.md` and `DECISIONS.md` for features, an ADR for any significant decision. New or changed endpoints appear in the generated API docs (Scribe).
8. **Commit** with a Conventional Commit message (`feat(real-estate): add property search`).
9. **Step summary** to the owner: what was built, how it was verified (commands and results), known limits, decisions needed.

Never say a step works without having run it.

## Non-negotiable invariants

1. **Ownership.** There are no tenants. Every row a user creates (a listing, a request, a reply, a booking, a message) has an owner column, and every read or write of someone else's row goes through a Policy. A feature test proves that user A cannot read private data of, update or delete user B's rows through the API. Cross-user access exists only in Filament, behind admin roles (ADR 0002).
2. **The platform's commission is protected.** An owner's phone number is never returned by the API for listings the platform brokers (`brokered = true`). Every chat opened and every viewing booked on such a listing creates a `leads` row in the same transaction. Lead rows are never updated or deleted; corrections are new rows.
3. **Money is never a float.** Use `Brick\Money\Money`, stored as `bigint` minor units (piasters) with a `currency` column, via `App\Support\Casts\MoneyCast`.
4. **Time is UTC** in storage and code (`CarbonImmutable`). Convert to Africa/Cairo only in presentation. One exception: places' opening hours are Cairo wall-clock times (`OpeningHoursCalculator`, DECISIONS 40).
5. **Locations are PostGIS.** Points are `geography(Point, 4326)`. Distances and "near me" are computed in the database (`ST_DWithin`, `ST_Distance`), never in PHP loops.
6. **Arabic text is normalized** before it is stored for search and before it is searched (`App\Support\Text\ArabicNormalizer`: alef forms, taa marbuta, alef maqsura, diacritics, tatweel). Search goes through Laravel Scout, so the engine can change from the database driver to Meilisearch by configuration.
7. **Jobs are idempotent** and receive ids, not models or payload blobs. Running a job twice must not send a push notification or a WhatsApp message twice.
8. **Secrets.** `env()` is called only in `config/*.php`. Never log tokens, OTP codes, or full phone numbers: hold numbers in `App\Support\PhoneNumber` (its string form is masked; `e164()` only where the real number is needed).
9. **Content is moderated.** A new listing, provider profile or place starts as `pending_review` and is visible in the public API only once an admin approves it. Status transitions go through an Action, never a direct update.
10. **The API is versioned and stable.** Routes live under `/api/v1`. Responses are API Resources; errors use one JSON shape (`message`, `code`, `errors`). A breaking change needs a new version.

## Code organization

```
app/
├── Domains/<Domain>/
│   ├── Actions/           # one use case per class
│   ├── Data/              # spatie/laravel-data DTOs
│   ├── Enums/
│   ├── Events/  Listeners/  Jobs/  Notifications/
│   ├── Exceptions/
│   ├── Models/
│   ├── Policies/
│   ├── Settings/          # spatie/laravel-settings classes: values admins tune
│   └── Support/           # pure logic (calculators, state machines), no I/O
├── Http/Controllers/Api/V1/<Domain>/   Http/Requests/Api/V1/<Domain>/   Http/Resources/V1/<Domain>/   Http/Middleware/
├── Filament/
└── Support/               # cross-cutting helpers only (casts, normalizers, value objects)
```

Domains (`docs/ARCHITECTURE.md` has their tables): Identity, Geography, RealEstate, Services, Places, Marketplace, Requests, Conversations, Bookings, Reviews, Favorites, Alerts, Notifications, Moderation, Commissions, Promotions, CityGuide, Media.

Dependency direction: **Http / Filament / Jobs → Actions → (Models, Support, other domains' Actions)**. Domain code never depends on `App\Http` or `App\Filament`. A domain writes only its own tables; to change another domain's data it calls that domain's Action. Reading through Eloquent relationships across domains is allowed.

## Laravel practices we follow

**Controllers.** Thin: authorize (Policy), validate (Form Request), call **one** Action, return an API Resource. Single-action controllers are invokable. No queries, business rules or `DB::` calls in controllers.

**Form Requests.** Every write endpoint has one. `authorize()` uses policies; `rules()` returns arrays, not pipe strings. Convert to a Data object with `$request->toData()`.

**Actions.** `final`, constructor-injected dependencies, one public `handle(...)` with typed parameters and return. Multi-row writes in `DB::transaction()`. Events with side effects implement `ShouldDispatchAfterCommit`. Named verb + noun: `PublishProperty`, `BookViewing`, `ReplyToRequest`.

**Models.** Relationships, casts (`casts()` method), scopes, small accessors. No workflows. `$fillable` explicit. `Model::shouldBeStrict()` outside production. Status and type columns are backed enums, stored as strings with a `CHECK` listing their values. Primary keys are UUID v7. A model in `App\Domains\<Domain>\Models` names its factory with `#[UseFactory]` and its policy with `#[UsePolicy]`. Relationships are declared on both sides of every foreign key. The morph map is enforced (`AppServiceProvider`): a model stored in a polymorphic column needs a short name there. Locations use `PointCast` and `HasLocation`; searchable text uses `HasSearchText`; reference data names use `HasTranslatedName`. Append-only tables (`leads`, `booking_events`, `commission_entries`) call `AppendOnly::enable()` in their migration and use `IsAppendOnly` on their model; their parents use `restrictOnDelete`.

**Data objects.** Inputs and outputs crossing a boundary are `spatie/laravel-data` classes, `final readonly` where possible.

**API Resources.** One per model per version, in `Http/Resources/V1`. Never return a model directly. Lists are paginated (cursor pagination for feeds).

**Jobs.** `final`, implement `ShouldQueue`, take ids, declare `$tries`, `backoff()`, `timeout` and `failed()`. Use `ShouldBeUnique`, `WithoutOverlapping` and `RateLimited` where they apply.

**Notifications.** Laravel Notifications with the `database` channel plus `fcm` (`laravel-notification-channels/fcm`). No paid SMS (ADR 0003). Device tokens live in `device_tokens`; a token FCM reports as invalid is deleted.

**Queries.** Eager-load what you use; select only needed columns on large tables; `lazyById()` / `chunkById()` for big sets. Every foreign key and every filter or sort column has an index in the same migration (GiST for geography, GIN trigram for searched text).

**Migrations.** Never edit a merged migration. Every migration has a working `down()`. `foreignUuid()->constrained()` with explicit `onDelete`.

**Configuration.** `env()` only in `config/`, read with `config('awez.*')`. Values an admin may tune (limits, durations, prices of featured packages, commission rates) are `spatie/laravel-settings` classes in the owning domain, edited in Filament. Inject dependencies through constructors; facades are fine in framework glue.

**Errors.** Domain errors extend `App\Exceptions\DomainException` (error code, translation key, HTTP status) and live in the domain's `Exceptions/`. Rendering is mapped once in `bootstrap/app.php`.

**Admin panel (Filament).** Every resource is authorized by a Policy and an admin role (`spatie/laravel-permission` with Filament Shield). Moderation, verification and commission screens record who did what (`spatie/laravel-activitylog`).

**Localization.** Every user-facing string is translated with `__()` (`lang/ar`, `lang/en`); Arabic is the default locale and the admin panel is RTL. The API returns messages in the locale of `Accept-Language`.

## Language

- Everything in the repository is written in English: code, comments, docs, commit messages, ADRs.
- Arabic appears only in `lang/ar`, in test data and fixtures, and in seeders of real place names.

## Code style

- `declare(strict_types=1);` in every PHP file. Classes are `final` unless designed for extension.
- Full native types on every parameter, return and property. Generics in PHPDoc. Larastan at max level.
- Descriptive English names, no abbreviations. Laravel naming: events past tense, listeners imperative, tables plural snake case, enum cases PascalCase.
- Small methods that do one thing. Comments explain _why_.
- No magic numbers. No `dd`, `dump`, `ray`, `var_dump` committed.
- Prefer early returns over nested conditionals.

## Tests

- **Pest 5.** `tests/Unit` (pure), `tests/Feature` (HTTP, jobs, DB), `tests/Arch` (rules).
- Feature tests use the real PostgreSQL from Docker with PostGIS. Never SQLite.
- API tests see the default locale (Arabic) unless they send `Accept-Language`.
- `Http::preventStrayRequests()` is on for all tests. Google, Apple, WhatsApp, FCM and storage are faked.
- Use factories, never hand-built rows. Moderated content from a factory is pending review, as new content is: use `published()` for public rows. A bug fix starts with a failing test.
- Run the tests after formatting, not only before.
- Coverage: `--min=85` for `app/Domains`. Mutation testing for money, commissions and booking state machines.

### Architecture tests (`tests/Arch`) enforce

- Pest presets: `php`, `security`, `laravel`.
- Strict types everywhere; no debug functions; `env()` only in `config`.
- Actions are final and expose a `handle` method.
- `App\Domains` does not use `App\Http` or `App\Filament`.
- Controllers do not use the `DB` facade and return API Resources.
- Jobs implement `ShouldQueue`.
- Form Requests are final and extend `FormRequest`.
- `App\Support` does not depend on `App\Domains`, `App\Http` or `App\Filament`.
- Models live only in `App\Domains\*\Models`, are final, use `HasUuids` and declare their fillable attributes.
- Every model whose table has a `user_id` or a `resident_id` has a policy.

Feature tests in `tests/Feature/Database` read the schema: an index behind every foreign key, GiST on every geography column, GIN trigram on every `search_text`, enum `CHECK`s that match the PHP enums, the delete rule of every foreign key, and relationships on both sides of every foreign key.

## Git

- `main` is the only long-lived branch. Work happens on short branches from `main`, merged by pull request once the definition of done holds. The owner merges.
- Small commits, Conventional Commits. One concern per PR. Migrations ship with the code that needs them.
- Commit messages and PR bodies carry no AI attribution lines (no `Co-Authored-By`, session links or "Generated with"): the owner's rule.
- Keys live only in the git-ignored `.env`. Before every commit, scan the staged diff for keys (`AIza`, `ghp_`, private keys, the Firebase service account).
- Never force-push or rewrite pushed history.
- Significant decisions get an ADR: `docs/adr/NNNN-short-title.md` (context, decision, consequences).

## When unsure

- Read `docs/ARCHITECTURE.md` and the closest existing domain; follow its patterns.
- Admin panel work follows `docs/design/ADMIN_PANEL.md`, the design the owner approved.
- Choose the boring, explicit option over the clever one.
- Ask before adding a new package. Prefer what Laravel already provides.
