# Task 004: Data model, part 2 (interaction and business)

- **Branch:** `task/004-interaction-data-model`, in `.worktrees/004-interaction-data-model` (`docs/tasks/README.md`).
- **Depends on:** task 003 (merged as #5).
- **Build plan step:** 1.3b. Before coding, expand it in `docs/BUILD_PLAN.md` into **Acceptance** and **Verify** lines from this brief, as 1.3a has them. Set this task's row to `in progress` (it is `planned` now).

## Why

The tables residents use to talk, book, review and save, and the tables the business runs on: leads, commission entries, featured placements and the city guide. With them the data model of `ARCHITECTURE.md` §3 is complete, except `otp_codes` and `device_tokens` (step 1.4) and `media` (step 2.1).

Follow task 003's patterns exactly: domain folders, UUID v7 keys, an index on every foreign key and every filter or sort column in the same migration, enums as strings with a `CHECK`, `MoneyCast`, `PointCast`, `#[UseFactory]`, `#[UsePolicy]`, the enforced morph map, reference data with `name_ar`/`name_en`.

## Housekeeping first

As `docs/tasks/README.md` says: task 003 is `done` (#5); remove its worktree (`sail down -v` inside it first) and its branches.

## 1. Append-only tables

Leads, booking events and commission entries are never updated or deleted (invariant 2 for leads; the other two are histories and money).

- `App\Support\Database\AppendOnly::enable('<table>')` in the migration: a PostgreSQL trigger that raises on `UPDATE` and `DELETE` (and its `down()` drops it). Corrections are new rows.
- A feature test per table: an update and a delete are refused by the database, through Eloquent and through `DB::table()`.
- Models of these tables refuse `save()` on an existing row and `delete()` in PHP too, with a clear exception, so the mistake shows before the database refuses it.
- A cascade from a parent would be refused by the trigger, so parents of append-only rows use `restrictOnDelete` (or `nullOnDelete` where the brief says so, which is an update: allowed only for the column the trigger names as nullable on deletion). Keep it simple: prefer `restrictOnDelete`, and say in `DECISIONS.md` which parent rows can therefore never be hard-deleted.

## 2. Tables

**Conversations**

- `conversations`: `subject` (`uuidMorphs`: property, provider, place, used item, request reply); `resident_id` FK users (the resident who opened it); `counterpart_id` FK users null (the advertiser or craftsman; **null when the platform answers**, which is the case for every brokered property, so the owner never appears); `status` (`open`, `closed`, `blocked`); `last_message_at`; `resident_read_at`, `counterpart_read_at`. One open conversation per resident and subject (partial unique index).
- `messages`: `conversation_id` `cascadeOnDelete`, `sender_id` FK users (staff answering for the platform are users too), `kind` (`text`, `location`, `system`), `body` null, `location` point null (a `CHECK`: text has a body, location has a point). Index on `(conversation_id, id)` for the feed (UUID v7 order, DECISIONS 53). Images come with media (2.1).

**Bookings**

- `bookings`: `kind` (`property_viewing`, `service_visit`); `bookable` (`uuidMorphs`: property or provider); `resident_id`; `counterpart_id` null (null for a brokered viewing, which the platform runs); `scheduled_at` (UTC); `address_note` null, `location` point null; `note` null; `estimated_price` + `currency` null; `is_urgent` bool (the urgent dispatch of step 3.3 adds its own offer table then); `status` (below). Indexes on `(status, scheduled_at)`, `resident_id`, `counterpart_id`.
- `booking_events` (append-only): `booking_id` `restrictOnDelete`, `from_status` null, `to_status`, `actor_id` FK users null (null: the system), `note` null, `created_at` only.
- **`App\Domains\Bookings\Support\BookingStateMachine`** (pure): the statuses `requested`, `confirmed`, `declined`, `on_the_way`, `in_progress`, `completed`, `cancelled_by_resident`, `cancelled_by_counterpart`, `no_show`, the allowed transitions between them, and who may make each (resident, counterpart, platform, system). `on_the_way` and `in_progress` exist for service visits only. Terminal statuses allow nothing. Unit tests for every allowed and every refused pair, and **mutation testing** (`CLAUDE.md`: booking state machines), score reported. Actions that use it come in step 3.1.

**Reviews**

- `reviews`: `booking_id` unique (one review per booking, and a review needs a booking: reviews after a finished booking only), `reviewer_id`, `reviewable` (`uuidMorphs`: provider or property), `rating` smallint with a `CHECK` 1–5, `comment` null, `reply` null and `replied_at` null (the reviewed side answers once), `is_hidden` bool, `hidden_by` null, `hidden_at` null. That the booking is `completed` is checked by the Action in step 3.2, not here.

**Favorites and alerts**

- `favorites`: `user_id`, `favoritable` (`uuidMorphs`: property, provider, place, used item), unique per user and item.
- `search_alerts`: `user_id`; `area` (the `requests.area` enum, reused); `query` null and its normalized `search_text` null; `filters` `jsonb` (shape defined by a Data class in step 3.4; here only `'{}'` by default); `district_id` null; `location` point null and `radius_m` null (both or neither, a `CHECK`); `frequency` (`instant`, `daily`); `is_active`; `last_notified_at` null.

**Notifications**

- Laravel's `notifications` table (`php artisan make:notifications-table`), with `uuidMorphs('notifiable')`. The `Notifiable` trait is already on `User`; a test stores and reads one database notification for a user.

**Commissions**

- `leads` (append-only): `property_id` `restrictOnDelete`; `resident_id` FK users `restrictOnDelete` (step 1.4 blanks a deleted resident's personal data instead of deleting the row, DECISIONS 41); `channel` (`chat`, `viewing`); `source` (`uuidMorphs`: the conversation or the booking that created it), unique per source; `voids_lead_id` null (self FK) and `void_reason` null: a correction is a new row that voids an earlier one (a `CHECK` that both are set or neither); `created_at` only.
- `commission_entries` (append-only, money): `property_id` and `property_agreement_id` `restrictOnDelete`; `lead_id` null; `kind` (`expected`, `invoiced`, `collected`, `cancelled`); `amount` + `currency`; `deal_price` + the same `currency` null; `occurred_on` date; `recorded_by` FK users; `note` null. A property's commission state is the sum of its entries, never a stored number. A small pure `CommissionBalance` in `Support` (expected, invoiced, collected, outstanding from a list of entries), unit and mutation tested.

**Promotions**

- `featured_packages`: `name_ar`, `name_en`, `applies_to` (`property`, `provider`, `place`, `used_item`), `duration_days`, `price` + `currency`, `is_active`, `sort_order`. Prices are still an open question (OPEN_QUESTIONS 2): seed none outside demo.
- `featured_placements`: `featurable` (`uuidMorphs`), `featured_package_id` null (null: granted by an admin), `user_id` null (the advertiser who bought it), `starts_at`, `ends_at` (`CHECK` ends after it starts), `price_paid` + `currency` null, `granted_by` FK users null, `cancelled_at` null. Index on `(featurable_type, featurable_id, ends_at)`. An `active()` scope (started, not ended, not cancelled), tested at the boundaries.

**CityGuide**

- `guide_entries`: `city_id`; `category` (`emergency`, `hospital`, `transport`, `government`, `utility`, `education`, `other`); `title`, `body` null; `phone_display` (text: emergency lines are three digits, `122`, which `PhoneNumber` rightly refuses; say why in a comment), `location` point null, `sort_order`, `is_published`. Admin content, one language (Arabic), with a `search_text`.
- `duty_pharmacies`: `place_id` (a pharmacy) `cascadeOnDelete`, `duty_date` date (a Cairo calendar date, the same exception as opening hours, DECISIONS 40), `starts_at`, `ends_at` (UTC), unique per place and date, index on `duty_date`.
- `news_items`: `city_id`, `title`, `body`, `search_text`, `is_published`, `published_at` null, `created_by` FK users.

Add every new morphable model to the morph map with a short name (`conversation`, `booking`, `review`, …).

## 3. Policies and ownership

- `Conversation` and `Message`: only the two participants (`resident_id` and `counterpart_id`) see them; a conversation answered by the platform is visible to its resident only (staff use Filament).
- `Booking`: the resident and the counterpart.
- `Review`: anyone sees one that is not hidden; only the reviewer sees their hidden one.
- `Favorite`, `SearchAlert`: the owner only.
- `FeaturedPlacement`: the advertiser who bought it.
- Leads, commission entries, featured packages and the city guide have no resident-facing policy rules beyond "not yours": they are admin data (Filament, step 1.5 onward). Make sure the arch rule "every model with `user_id` has a policy" still holds, and extend it to `resident_id`.
- Ownership proven at the policy level, as in task 003, for every table with an owner. In particular: user A cannot read B's conversation or messages, and no resident can read a brokered conversation's internal fields that would identify the property's owner (there are none: `counterpart_id` is null; test it).

## 4. Seeders

- **Reference data** (everywhere): Sadat City's emergency numbers in `guide_entries`: الشرطة 122، الإسعاف 123، المطافئ 180، طوارئ الكهرباء 121، طوارئ الغاز 129، طوارئ المياه 125. Add a **Review** entry in `DECISIONS.md` so the owner confirms them, and asks for the city's hospitals and transport lines.
- **Demo content** (local and testing only), built on task 003's demo city: conversations with messages (some on brokered properties, answered by a demo staff user), bookings in every status with their events, reviews on completed bookings only, favorites, search alerts, leads for every brokered conversation and viewing (with one voided by a correction), commission entries for the property with an agreement, two featured packages and a few placements (one active, one ended, one cancelled), duty pharmacies for the next 14 days, and a few news items.
- Report the time of `migrate:fresh --seed` on `testing`.

## Out of scope

Actions, endpoints and Filament screens: chat (2.8), bookings and their timeline (3.1), reviews and badges (3.2), urgent dispatch's offers table (3.3), notifications and alerts (3.4), city guide (3.5), commissions (4.3), featured listings (4.1). The rule that a chat or a viewing on a brokered listing creates a lead **in the same transaction** belongs to those Actions; this task only gives the table.

## Acceptance

- `sail composer check` green; test counts, and the mutation scores of `BookingStateMachine` and `CommissionBalance`, in the report.
- Migrations verified on `testing` as the definition of done says: `migrate:fresh --seed`, `migrate:rollback --step=<this task's migrations>`, `migrate` again.
- Real run, pasted in the report:
    - `\d leads` showing the trigger, the constraints and the indexes;
    - a psql `UPDATE` and `DELETE` on `leads` refused by the trigger;
    - row counts per new table after seeding, and the demo bookings by status.
- Every new decision in `DECISIONS.md`; `ARCHITECTURE.md` §3 updated where tables differ from it.
