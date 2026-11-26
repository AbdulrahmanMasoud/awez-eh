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
| Identity | `users`, `social_accounts`, `phone_verifications`, `device_tokens`, `personal_access_tokens`, `roles`, `permissions` (and their pivots), `activity_log` |
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
| Media | `media` (spatie/laravel-medialibrary), `pending_uploads`, `settings` (spatie/laravel-settings, every domain's admin-tuned values) |

## 4. Identity and access

- **Sign-in** is with Google or Apple (ADR 0003). The app gets an ID token natively; the API verifies it against the provider's published keys (`IdentityTokenVerifier`) and finds the user by `social_accounts (provider, subject)`, else by a verified email, else creates one. No passwords and no paid SMS.
- **Tokens:** each device gets a Sanctum personal access token named after it; signing in again on the same device replaces it. A device's push token (`device_tokens`) belongs to its access token and goes with it. Tokens are revoked on sign-out and on account deletion.
- **The phone number** is confirmed separately, through `PhoneVerifier`: a `log` driver during development (the code goes to its own log channel) and, from step 4.3b, a code the resident sends us on WhatsApp. `phone_verifications` holds the hashed codes and is what the rate limits count. `users.phone` holds only a confirmed number.
- **Statuses:** an account is `active`, `suspended` (refused on every signed-in route) or `deleted`.
- **Deleting an account anonymizes it** (DECISIONS 72): the row stays, because conversations, bookings, reviews and leads point at it. Its sign-in links, tokens and personal data go, and each domain archives or deletes its own content through its own Action (brokered properties are archived and kept).
- **Roles:** every user is a resident; a user is also an advertiser (owns listings or places) and a provider (has a craftsman profile) by what they own, not by a column. Admins sign in to the Filament panel at `/admin` with email, password and a required authenticator app, and hold admin roles (`super_admin`, `moderator`, `data_entry`, `sales`) through `spatie/laravel-permission`, named the way Filament Shield names permissions. An admin permission counts only inside the panel: through the API an admin is a resident (DECISIONS 86). Admins are made with `awez:create-admin`.
- **Activity log:** every admin action is recorded (`activity_log`, log `admin`): who, what, which record, why, before and after, never personal data.
- Ownership is enforced by Policies (ADR 0002). The account endpoints take no user id: they answer for the token's own account.

## 5. Photos and documents

- **Cleaned before stored** (ADR 0007): every image is decoded and written again as JPEG, upright, without metadata (location, camera, names), at most 2560 px on its long side. JPEG, PNG and WebP are accepted by content; HEIC is not (no decoder in the container).
- **Two sizes in WebP**, `thumb` (480 px wide) and `large` (1600 px), made on the `media` queue. They are a photo's only public URLs: its original stays on the private disk.
- **Private documents** (agreements' scans) have no public form; an allowed admin opens one through a signed URL for 5 minutes, logged each time.
- **Uploading ahead of the listing:** the app uploads to `POST /api/v1/uploads` (a pending upload, the uploader's for 24 hours), then sends the uploads' ids when it creates the listing; `AttachUploads` hands the files over in order, within the collection's limit. An hourly job deletes what was never attached.
- **Storage** is configuration: the local disks now, Cloudflare R2 (`r2`, `r2_private`) when `.env` says so.
- Collections and their limits are declared on each model (`InteractsWithMediaCollections`); the limits are `MediaSettings`, edited on إعدادات الصور.

## 6. Content lifecycle

`draft → pending_review → published → (paused | sold/rented | rejected | archived)`. Transitions are Actions in the owning domain; each writes an activity log entry. Only `published` rows are visible in public endpoints. Properties, providers, places and used items are moderated this way; requests are public as soon as they are written, while open and not expired, and reports handle abuse.

## 7. Search and location

- Text search goes through Scout with the database driver, on a `search_text` column per searchable table, normalized by `ArabicNormalizer` (`HasSearchText`), backed by a `pg_trgm` GIN index for typo tolerance. A term is compared with the text's words (`strict_word_similarity`), not with the whole text (DECISIONS 38 and 39).
- Maps in the app use MapLibre with OpenStreetMap tiles from OpenFreeMap; residents pick a location by dropping a pin, so no paid geocoding is needed (ADR 0005).
- Locations are `geography(Point, 4326)` with GiST indexes. "Near me" and urgent dispatch use `ST_DWithin` and order by `ST_Distance`.

## 8. Commission protection

For a listing with `brokered = true`, the API never returns the owner's phone number, and the platform's staff answer its conversations and run its viewings: neither has a counterpart, so the owner never appears. Opening a chat or booking a viewing creates a `leads` row (resident, listing, channel, source) in the same transaction. Leads, booking events and commission entries are append-only: PostgreSQL triggers refuse every update and delete, and a correction is a new row that voids an earlier one. A property's commission is the sum of its entries, never a stored number.

## 9. Notifications

Laravel Notifications with the `database` and `fcm` channels; no SMS. Topics per city and district for broadcasts (the duty pharmacy, city news). Sending happens in queued jobs.

## 10. API conventions

- Base path `/api/v1`, JSON only (whatever the client sends as `Accept`), `Accept-Language: ar|en` with Arabic as the default, answered with `Content-Language`.
- Errors: `{ "message": "...", "code": "snake_case_code", "errors": { "field": ["..."] } }`. The message is translated; models, ids, exception messages and traces never reach the client (`App\Http\ApiExceptionRenderer`).
- Every request carries a `request_id` (`X-Request-Id` in and out), which Laravel's Context adds to each log line and to queued jobs.
- The `api` rate limiter counts signed-in users per user and guests per IP address.
- Lists: cursor pagination for feeds, page pagination for admin-like lists.
- Documentation is generated by Scribe from routes, Form Requests and Resources.
