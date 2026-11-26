# Task 008: Real estate

- **Branch:** `task/008-real-estate`, in `.worktrees/008-real-estate` (`docs/tasks/README.md`).
- **Depends on:** task 007 (merged as #13).
- **Build plan step:** 2.2. Before coding, expand it in `docs/BUILD_PLAN.md` into **Acceptance** and **Verify** lines from this brief.

## Why

Real estate is where the platform earns its commission, and the first area residents use. It is also the first content domain with endpoints and moderation, so the patterns set here (the status machine, the owner's endpoints, the review screen) are the ones steps 2.3 to 2.5 copy. Build them to be copied.

## Housekeeping first

As `docs/tasks/README.md` says. There is an `awez:create-admin` prompt left waiting in the main app container from earlier: leave it; it is the owner's.

## 1. The content status machine (shared)

- `App\Domains\Moderation\Support\ContentStatusMachine` (pure): the transitions of `ContentStatus` (ARCHITECTURE §5) and who makes each:
    - owner: `draft → pending_review` (submit), `published → paused`, `paused → pending_review` (resume, reviewed again only if edited while paused; otherwise straight to `published`: decide and record), `published|paused → sold|rented` (by kind: `sold` for sale, `rented` for rent, used items `sold` only), any non-terminal → `archived`;
    - moderator: `pending_review → published` (approve), `pending_review → rejected` (reason required), `published → rejected` (take down, reason required);
    - owner again: `rejected → pending_review` after an edit.
    - Terminal: `sold`, `rented`, `archived`.
- Unit tests for every pair and actor, **mutation tested** (report the score). It is reused by providers, places and used items, so keep it free of anything property-specific (the sold/rented rule takes the kind as input).
- `App\Domains\Moderation\Actions\TransitionContentStatus` is not the way: each domain owns its rows, so each domain has its own small Actions that consult the machine (`SubmitProperty`, `ApproveProperty`, `RejectProperty`, `PauseProperty`, `ResumeProperty`, `MarkPropertySold`, `MarkPropertyRented`, `ArchiveProperty`). Each writes an activity entry and dispatches an event (`PropertyApproved`, `PropertyRejected`, …, `ShouldDispatchAfterCommit`).
- **Editing a published property:** changing only the price keeps it published; any other field, or new photos, sends it back to `pending_review` and hides it until approved. Record this in `DECISIONS.md` as a **Review** item for the owner.
- The owner is told about approval and rejection (with the reason) by a database notification (Laravel's, translated); push comes in step 3.4.

## 2. Public endpoints

- `GET /api/v1/properties`: published only, cursor pagination (20).
    - Filters: `purpose`, `type` (several), `district_id` (several), `price_min`, `price_max` (in pounds; converted to piasters through `Money`, never a float), `area_min`, `area_max`, `bedrooms_min`, `furnished`, `finishing`, `brokered`, and `near` (`lat`, `lng`, `radius_m` up to 20 km, through `withinMeters`).
    - Sort: `newest` (default, by id), `price_asc`, `price_desc`, `nearest` (requires `near`).
    - Every filter and sort column already has its index (task 003); add any missing one, and show one `EXPLAIN` per sort in the report.
    - Text search is step 2.6: no `q` yet.
- `GET /api/v1/properties/{id}`: one published property (404 for anything else, unless it is the requester's own).
    - It counts a view, at most once a day per signed-in user or per IP for guests (cache), never the owner's own.
- `GET /api/v1/real-estate/options`: the enums (purpose, type, finishing, rent period) with their Arabic and English labels, and the districts, for the app's filters. Cached; the cache clears when an admin changes a district.
- `PropertyResource` (public): everything a resident needs, the photos (`MediaResource`), the district, the advertiser (name, avatar, member since, has a confirmed phone), `brokered`, and the **location rounded to about 100 m** (3 decimals): the exact point of someone's home is shown to its owner and to admins only. Record this.
- **Contact** (invariant 2):
    - `POST /api/v1/properties/{id}/contact` (signed in, rate limited): for a non-brokered property, the owner's phone (E.164) and a `wa.me` link; for a **brokered** one, a domain error `contact_through_platform` (409) telling the app to open a chat or book a viewing (steps 2.8 and 3.1).
    - No response anywhere (list, detail, contact, the advertiser block, errors) ever contains the owner's phone or user id of a brokered property. One test walks every property endpoint with a brokered property and searches the whole JSON for the owner's number, id, email and name.

## 3. The owner's endpoints

- `POST /api/v1/properties`: creates a property with its photos (`photos`: upload ids, through `AttachUploads`), as `draft`, or as `pending_review` when `submit: true`. The location is required (a pin from the map; ADR 0005). `brokered` cannot be set by a resident.
- `PATCH /api/v1/properties/{id}`: edits (section 1's rule on review). `photos` here is the **whole ordered list** the resident wants: existing media ids and new upload ids; anything missing is removed.
- `POST /api/v1/properties/{id}/submit`, `/pause`, `/resume`, `/sold`, `/rented`, `/archive`: one invokable controller each, calling one Action.
- `GET /api/v1/me/properties`: the resident's own, every status, with `rejection_reason` and what they may do next (`allowed_actions`, from the machine).
- Limits in `App\Domains\RealEstate\Settings\RealEstateSettings` (an admin page under النظام, like إعدادات الصور): active listings per account (10), new listings per day (5). Over a limit: a domain error with its code.
- Ownership at the API level (invariant 1): resident B cannot edit, submit, pause, archive or see the draft of A's property, nor attach A's uploads.
- Form Requests with Arabic attribute names, Data objects (`spatie/laravel-data`), Scribe docs with Arabic examples and every error code.

## 4. The admin panel

Follow `docs/design/ADMIN_PANEL.md`. The المحتوى and المراجعة groups appear now.

- **المراجعة** (moderators and super admins): the queue of everything `pending_review`, oldest first, with the kind tabs of the design. Properties only for now, built so steps 2.3 to 2.5 add their kinds by registering them, not by editing the page. Each row: approve in one click; reject with a required reason (a select of common reasons, translated, plus free text). The sidebar badge counts the queue.
- The dashboard's **محتاج قرارك** widget, from `ADMIN_PANEL.md`, on the same queue.
- **العقارات** under المحتوى: list with status tabs, filters (purpose, type, district, brokered, owner), the photos column; the view page with the photos (the media field from task 007), the map point (latitude and longitude for now), the owner (masked phone, "show number" as in task 006), the status history from the activity log, and the actions the machine allows for an admin. Admins do not edit residents' text; a moderator who wants a change rejects with a reason.
- **Brokered properties** (`sales` and super admins):
    - a sales admin creates a brokered property for an owner who signed an agreement (the owner may have no account: then the property belongs to a platform account, `awez:platform-account` creates it once, record this);
    - or marks an existing property brokered by adding its agreement;
    - **الاتفاقيات** as a relation manager on the property: create (owner name, owner phone, rate in basis points or a fixed amount, dates, notes, the private documents from task 007), view, no delete (it is history);
    - a brokered property's owner phone is shown in the panel to sales and super admins only.
- Every admin action writes an activity entry.

## 5. Demo and seeders

The demo city from task 003 already has properties. Add photos to the demo properties (generated images are fine, a few per property, through the real conversion path), so the app and the panel show pictures.

## Out of scope

Text search (2.6), chat and leads (2.8), viewings (3.1), featured placements (4.1), commission entries' screens (4.3), a map picker in the panel.

## Acceptance

- `sail composer check` green; test counts and the mutation score of `ContentStatusMachine` in the report.
- Migrations, if any, verified on `testing` as the definition of done says.
- Real run on the dev stack, outputs in the report: a resident (fake sign-in) creates a property with two uploads and submits it; it is absent from the public list; an admin approves it in the panel (or through the Action in `tinker` if no browser); it appears; the filters and each sort; the contact endpoint for a normal and a brokered property; an edit that sends it back to review; `me/properties` with `allowed_actions`; the brokered JSON search showing no trace of the owner.
- Screenshots of المراجعة and a property's page if you can drive a browser; otherwise the pages answer 200 for the right roles and 403 for the others.
