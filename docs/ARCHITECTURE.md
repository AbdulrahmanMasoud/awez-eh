# Architecture

## 1. What the system does

One city app with five kinds of content, one way to ask for anything, and the tools to trust the people behind it.

| Area | What a resident does | What the platform does |
| --- | --- | --- |
| Real estate | Browses sale, rent, land and commercial listings, on a list or a map; compares; books a viewing | Brokers listings it has a written agreement for, hides the owner's number, records every lead, takes a commission |
| Services | Finds a craftsman by trade and rating; books a visit; asks for "a craftsman in 30 minutes" | Verifies craftsmen, dispatches urgent requests to the nearest available ones, keeps reviews honest |
| Places | Finds restaurants, shops and pharmacies open now and near; sees menus and offers; sends a prescription photo | Publishes places and their offers; the duty pharmacy of the night |
| Marketplace | Buys and sells second-hand items | Moderates listings |
| Requests | Writes "what do you want" when search finds nothing | Routes the request to matching advertisers and craftsmen, who reply |
| City guide | Emergency numbers, transport, news, later market prices and outages | Admins publish and update it |

Across them: chat between a resident and an advertiser, bookings with a status timeline, reviews after a finished booking only, favorites, search alerts, notifications, featured listings, an advertiser dashboard and a craftsman mode.

## 2. Components

```
Mobile app (Expo, later)  ─┐
Filament admin panel      ─┼──>  Laravel (API /api/v1, Sanctum, Queues, Scheduler, Reverb later)
Public listing pages (later)┘          │
                                       ├── PostgreSQL 18 + PostGIS + pg_trgm
                                       ├── Redis (cache, queues)
                                       ├── Cloudflare R2 (images, two sizes)
                                       ├── Google and Apple sign-in, WhatsApp Cloud API (phone confirmation)
                                       └── Firebase Cloud Messaging (push)
```

Added only when measured need appears (`docs/BUILD_PLAN.md`, Phase 6): Horizon, Meilisearch (through Scout), Octane.

## 3. Domains and their tables

| Domain | Tables |
| --- | --- |
| Identity | `users`, `otp_codes`, `device_tokens`, `personal_access_tokens` |
| Geography | `cities`, `districts` |
| RealEstate | `properties`, `property_agreements` |
| Services | `trades`, `providers`, `provider_trades`, `provider_prices` |
| Places | `place_categories`, `places`, `place_items`, `place_offers`, `opening_hours` |
| Marketplace | `used_items` |
| Requests | `requests`, `request_replies` |
| Conversations | `conversations`, `messages` |
| Bookings | `bookings`, `booking_events` |
| Reviews | `reviews` |
| Favorites | `favorites` |
| Alerts | `search_alerts` |
| Notifications | `notifications` (Laravel's) |
| Moderation | `reports`, moderation status columns on owned content |
| Commissions | `leads`, `commission_entries` |
| Promotions | `featured_packages`, `featured_placements` |
| CityGuide | `guide_entries`, `duty_pharmacies`, `news_items` |
| Media | `media` (spatie/laravel-medialibrary) |

## 4. Identity and access

- Sign-in is with Google or Apple; the phone number is confirmed by a code the resident sends us on WhatsApp, with a `log` driver during development (ADR 0003). No passwords and no paid SMS.
- A verified code issues a Sanctum personal access token, named after the device. Tokens are revoked on sign-out and on account deletion.
- Roles: every user is a resident; a user may also be an advertiser (owns listings or places) and a provider (craftsman). Admins sign in to Filament with email, password and two-factor authentication, and hold admin roles (`super_admin`, `moderator`, `data_entry`, `sales`).
- Ownership is enforced by Policies (ADR 0002).

## 5. Content lifecycle

`draft → pending_review → published → (paused | sold/rented | rejected | archived)`. Transitions are Actions in the owning domain; each writes an activity log entry. Only `published` rows are visible in public endpoints.

## 6. Search and location

- Text search goes through Scout with the database driver, on columns normalized by `ArabicNormalizer`, backed by `pg_trgm` GIN indexes for typo tolerance.
- Maps in the app use MapLibre with OpenStreetMap tiles from OpenFreeMap; residents pick a location by dropping a pin, so no paid geocoding is needed (ADR 0005).
- Locations are `geography(Point, 4326)` with GiST indexes. "Near me" and urgent dispatch use `ST_DWithin` and order by `ST_Distance`.

## 7. Commission protection

For a listing with `brokered = true`, the API never returns the owner's phone number. Opening a chat or booking a viewing creates a `leads` row (resident, listing, channel, time) in the same transaction. Leads are append-only.

## 8. Notifications

Laravel Notifications with the `database` and `fcm` channels; no SMS. Topics per city and district for broadcasts (the duty pharmacy, city news). Sending happens in queued jobs.

## 9. API conventions

- Base path `/api/v1`, JSON only, `Accept-Language: ar|en`.
- Errors: `{ "message": "...", "code": "snake_case_code", "errors": { "field": ["..."] } }`.
- Lists: cursor pagination for feeds, page pagination for admin-like lists.
- Documentation is generated by Scribe from routes, Form Requests and Resources.
