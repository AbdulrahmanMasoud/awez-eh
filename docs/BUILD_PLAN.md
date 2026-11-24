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

- [ ] **1.1 Project skeleton and quality gate.** (task 001)
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
- [ ] **1.2 Core support.** `AppServiceProvider` defaults (strict models, `CarbonImmutable`, destructive commands prohibited and HTTPS forced in production), `DomainException` with one JSON error shape for `/api/*`, `MoneyCast` on `brick/money` (EGP piasters), `PhoneNumber` for Egyptian mobile numbers with `mask()`, `ArabicNormalizer`, a `request_id` in every log line, Arabic as the default locale, `/api/v1` route group with `Accept-Language`.
- [ ] **1.3 Data model.** Migrations, enums, models and factories for every table in `docs/ARCHITECTURE.md` §3, with their indexes (GiST for geography, GIN trigram for searched text). A seeder with Sadat City and its districts, trades and place categories, and demo content.
- [ ] **1.4 Sign-in.** Sign in with Google and Apple (verified server-side from their ID tokens), a phone-verification interface with a `log` driver for local development and tests (rate limits per number and per IP), Sanctum tokens per device, `device_tokens`, sign-out, account deletion, user roles. No paid SMS (ADR 0003).
- [ ] **1.5 Admin panel foundation.** Filament with RTL Arabic, admin sign-in with 2FA, admin roles (`spatie/laravel-permission` + Filament Shield), activity log, resources for cities, districts, trades, categories and users.

## Phase 2 — The first version (what residents need on launch day)

- [ ] **2.1 Media.** Image upload to R2 with two sizes through `spatie/laravel-medialibrary`.
- [ ] **2.2 Real estate API.** Listing, filters, sort, detail, create and edit by owner, moderation in Filament, brokered listings that hide the owner's number.
- [ ] **2.3 Services API.** Providers by trade, rating and availability; profile with prices and work photos; verification in Filament.
- [ ] **2.4 Places API.** Restaurants, shops, pharmacies; items, offers, opening hours, "open now".
- [ ] **2.5 Marketplace API.** Second-hand items by category, negotiable flag.
- [ ] **2.6 Search.** Scout on the database driver across all areas, Arabic normalization, typo tolerance, "near me".
- [ ] **2.7 Requests.** Create a request, matching advertisers see it, replies, views count.
- [ ] **2.8 Chat.** Conversations and messages through the API (polling first), leads recorded for brokered listings.
- [ ] **2.9 Favorites, reports and blocking.**

## Phase 3 — Interaction and trust

- [ ] **3.1 Bookings** with their state machine and events timeline; viewings of brokered properties create leads.
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
