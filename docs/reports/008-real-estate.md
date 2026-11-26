# Report 008: Real estate

- PR: #16 (https://github.com/AbdulrahmanMasoud/awez-eh/pull/16). CI: green on 94d92cf (run 45823271467); see the PR for the report commit.
- Branch: task/008-real-estate. Commit: 94d92cf (the last before this report).

## What changed

- **Residents can list properties from the app.**
    - A resident with a confirmed number creates a property with its photos (uploaded beforehand) and the pin they dropped.
    - It is saved as a draft or sent to review at once. Nobody else sees it until a moderator approves it.
    - Limits an admin can change on إعدادات العقارات: 10 open listings per account, 5 new ones a day.
- **Owners manage their listings.**
    - "My properties" shows each listing in its status, with the rejection reason and what the owner may do next: send to review, pause, resume, sold (a sale), rented (a rent), archive.
    - Changing only the price keeps a listing public. Changing anything else, or adding a photo, hides it until a moderator approves it again (a decision for you below). Removing or reordering photos keeps it public.
    - A rejected listing goes back to review once its owner edits it. A paused listing edited while paused goes through review before it is public again.
- **Everyone can browse.**
    - The list shows published properties only, 20 a page.
    - Filters: sale or rent, types, districts, price range in pounds, area, bedrooms, furnished, finishing, brokered, and "near me" within up to 20 km.
    - Sorts: newest, cheapest, most expensive, nearest.
    - A property's page counts a view, once a day per person, never its owner's.
    - The app gets the filters' options (with Arabic and English labels, and the districts) from one cached endpoint.
- **A home's exact location stays private.** Residents see the pin rounded to about 100 m. Only the owner and admins see the exact point.
- **The commission is protected.**
    - For a property the platform brokers, the advertiser shown is the platform itself.
    - "Show me the owner's number" answers that the owner is reached through the platform (chat or viewing, coming in steps 2.8 and 3.1).
    - No answer anywhere carries the brokered owner's number, account, email or name; a test and the real run searched every endpoint for them.
    - For other properties, the button gives the owner's number and a WhatsApp link, up to 30 times an hour per resident.
- **Owners hear about reviews.** Approval, rejection and take-down each send the owner a notification in their own language, with the reason. Push notifications come in step 3.4.
- **The admin panel has its المراجعة and المحتوى sections.**
    - المراجعة (moderators and super admins) lists everything waiting, oldest first, with a tab per kind (properties now; craftsmen, places and used items join in steps 2.3 to 2.5), and a badge in the sidebar.
    - Approve is one click. Reject asks for a reason from a list (unclear photos, missing details, wrong price, duplicate, wrong category, contact details in the text) or free text, sent to the owner in their language.
    - The dashboard's محتاج قرارك shows the five oldest, with the same buttons.
    - العقارات lists every property with a tab per status and filters.
    - A property's page shows its photos, details, exact point, owner (number masked; "show number" for the roles allowed it, logged), its status history (who did what, when, why) and the moves allowed now.
- **Sales lists brokered properties.**
    - A sales admin adds a brokered property with its signed agreement, for the owner's account or, when the owner has none, for the platform's own account. It waits for review like any listing.
    - On any property, recording an agreement makes it brokered.
    - Agreements are kept as history: recorded and opened, never edited or deleted. Their scanned documents open through 5-minute links, each one logged.
    - Only sales and super admins see a brokered owner's number.
- **Demo photos:** each demo property has three drawn photos (a building under a sky), cleaned and converted like real ones, so the app and the panel show pictures.
- **Fixed on the way:** masked numbers in the panel read reversed inside Arabic text (`678*****010`); they read left to right now, on the users' screen too.

## Decisions for the owner

1. **Editing a published property** (DECISIONS 108, OPEN_QUESTIONS 9).
    - Done meanwhile: only a change of price, or removing or reordering photos, keeps it public. Any other field, or a new photo, hides it until a moderator approves it again.
    - Costs:
        - as done: a typo fix in the description hides the listing for a while;
        - a looser rule (say, the description too): a listing can be changed into something else after approval without review.
2. **Resuming and resubmitting** (DECISIONS 110).
    - Done meanwhile:
        - a paused listing comes back at once unless it was edited while paused;
        - a rejected listing can be sent again only after an edit (any edit, the price included).
    - Costs:
        - as done: an owner cannot resend a refused listing unchanged;
        - the other way: the same refusal can come back to the queue again and again.
3. **Listing needs a confirmed phone number** (DECISIONS 112).
    - Done meanwhile: without one, creating a listing answers `phone_required`, since the number is how others reach the owner.
    - Costs:
        - as done: an owner confirms their number before their first listing;
        - the other way: drafts without a number, refused only at submit.
4. **A brokered property that sales adds waits for review** (DECISIONS 117).
    - Done meanwhile: invariant 9 says new listings start pending review, and sales cannot approve, so a moderator or super admin approves it.
    - Costs:
        - as done: two admins for each brokered listing;
        - the other way: sales publishes directly, which needs an exception to invariant 9 in CLAUDE.md.
5. **The platform account** (DECISIONS 117). Brokered properties whose owner has no account belong to one account, `platform@awezeh.com` (`AWEZ_PLATFORM_ACCOUNT_EMAIL`). It is created once by `awez:platform-account` and cannot sign in. If you prefer another email, set it before running the command.
6. **What sales and moderators hold by default** (DECISIONS 116).
    - Moderators approve, reject and take down, and see a resident owner's number.
    - Sales adds brokered properties and agreements and sees brokered owners' numbers, but does not approve.
    - A super admin can change both on the roles screen.

## The owner's steps

1. After the deploy runs the migrations, run `php artisan db:seed --class=ReferenceDataSeeder --force` once, so the existing roles receive the new permissions (المراجعة, العقارات, agreements, إعدادات العقارات). Seeding takes nothing away.
2. Run `php artisan awez:platform-account` once (it says "Created" the first time, "Found" after).
3. Optional in Dokploy (the defaults are below):
    - `AWEZ_CONTACT_RATE_LIMIT_PER_HOUR` (30);
    - `AWEZ_PLATFORM_ACCOUNT_EMAIL` (`platform@awezeh.com`);
    - `AWEZ_DEMO_PHOTOS`, which matters only where demo content is seeded (local).
4. Check after the deploy:
    - a moderator sees المراجعة with its badge;
    - a sales admin sees العقارات and "إضافة عقار", but not المراجعة;
    - `GET /api/v1/real-estate/options` answers with the districts.

## Verification

**`sail composer check`:** Pint, Rector and Larastan (max, no baseline, no ignores) pass. Pest passes 1,761 tests with 5,797 assertions. `main` had 1,148; this task adds 613:

| File | Tests | What it proves |
| --- | --- | --- |
| `Unit/Domains/Moderation/ContentStatusMachineTest` | 473 | every status × action × deal × changed flag × actor against a separate specification; allowed actions; terminal and editable statuses; the edit rule; reasons; who makes each action |
| `RealEstate/PropertyLifecycleTest` | 11 | each Action's move and columns (reviewer, dates, first publication kept); reasons required; resubmit only after a change; resume through review when edited; sold/rented by purpose; `action_not_allowed` with what is allowed; the two logs; events after commit; the owner's notification in their language |
| `RealEstate/PublicPropertiesTest` | 29 | published only; cursor pages; every filter; prices to the piaster; each sort, nearest paged by cursor; the radius and its default; refused filters; the rounded location and the owner's exact one; the advertiser; 404s; views once a day per account or IP, never the owner, without touching `updated_at`; the options and their cache |
| `RealEstate/OwnerPropertiesTest` | 28 | create as draft or to review; `brokered`/status/owner/views never taken from a resident; phone required; Arabic validation messages; the limits (HTTP with the defaults, and the settings through the Action); someone else's uploads; the edit rule (price only, each other field, new photo vs removed/reordered); removed files deleted; paused and rejected changes; no-op edits; rent period rules; not editable; every owner endpoint; `me/properties` |
| `RealEstate/PropertyOwnershipTest` | 19 | with real tokens: B never sees, edits, archives or contacts A's unpublished property (404); B cannot change or move A's published one (403); A's uploads and A's photos are refused to B; guests must sign in |
| `RealEstate/BrokeredPropertyTest` | 6 | the number and `wa.me` link; `contact_through_platform`; `contact_unavailable`; 30 an hour; every endpoint searched for a brokered owner's number (four forms), id, email and name; the owner's own view |
| `Admin/ReviewQueueTest` | 11 | المراجعة for moderators and super admins only; oldest first; approve in one click into the log; reject needs a reason, written in the owner's language; the badge; kinds per role; محتاج قرارك (five rows, approve) and who sees it |
| `Admin/PropertiesResourceTest` | 33 | العقارات per role; status tabs and filters; the page (point, masked owner, history); moves per status; take down; sales cannot review; brokered number for sales and super admins only, logged; a resident's number for moderators; brokered creation for an account and for the platform account; form checks; recording an agreement; agreements per role, no edit or delete; documents through signed URLs; إعدادات العقارات and its log; the platform account command |
| `Database/DemoPhotosTest` | 2 | three photos per property through the real path, once; refused outside local and testing |
| `Admin/UsersResourceTest` | +1 | masked numbers written left to right |

- Adjusted tests: the moderation columns and the new partial indexes (`SchemaTest`), the seeded roles' permissions, the sidebar per role, and the account routes' parameters (`property` beside `upload`).
- **Mutation testing** of `ContentStatusMachine`: 49 mutations, score 100%.
- **Coverage** of `app/Domains`: 97.2% (1,611 of 1,658 lines).
- **Tests first:** the machine's specification test was written before the machine. The API and panel tests were written right after each piece's code, in the same commit or the next, not strictly before it.
- **Migrations on `testing`:**
    - `migrate:fresh --seed` succeeded, with 90 demo photos on 30 properties;
    - `migrate:rollback --step=2` rolled back this task's two migrations (the columns and indexes, and the settings);
    - `migrate` applied them again.

**Real run** on the worktree's own stack (http://localhost:8032, its own database, fake sign-in on):

- A resident signed in (`fake:` token), confirmed `01023456789` with the code from `storage/logs/phone-verification.log`, and uploaded two photos.
- They created a rent with both photos and `submit: true`:
  ```
  POST /properties -> 201
  {"status": "pending_review", "price": {"amount": "7250.50", "currency": "EGP"}, "rent_period": "monthly", "location": {"lat": 30.381234, "lng": 30.526789, "exact": true}, "allowed_actions": ["archive"], "brokered": false}
  in the public list: False | guest GET /properties/{id}: 404
  ```
- A moderator signed in to the panel in the browser (password and authenticator code) and approved it in المراجعة in one click. Then:
  ```
  in the public list: True (first, newest)
  guest detail: location {'lat': 30.381, 'lng': 30.527, 'exact': False}; advertiser {"is_platform": false, "name": "realrun.resident", "member_since": "2026-11-26", "has_confirmed_phone": true}; photos 1500×2000 and 2000×1500, processed
  notification: {"type": "property_approve", "title": "إعلانك اتنشر", "body": "عقارك «شقة للإيجار 3 غرف قريبة من الجامعة» بقى ظاهر للناس.", "reason": null}
  ```
- They rejected another listing with "السعر مش منطقي." plus details; the row left the queue and the tab's count went from 3 to 2.
- Filters and sorts on the demo data plus this property:
  ```
  purpose=rent: 9 | type[]=apartment&type[]=villa: 11 | price 5,000 to 8,000: 4 | area 100 to 150: 10 | bedrooms_min=3: 11
  furnished=true: 3 | finishing=finished: 5 | brokered=true: 9 | within 1 km of the centre: 2
  sort=newest: first prices [7250.5, 4463000, 4700, 4762000]
  sort=price_asc: [2800, 3600, 4200, 4700] | sort=price_desc: [8526000, 4762000, 4463000, 4387000]
  sort=nearest: [7250.5 (the pin at the centre), 5500, 4762000, 1264000] | nearest without a location: 422
  ```
- Contact:
  ```
  normal: 200 {"phone": "+201023456789", "whatsapp_url": "https://wa.me/201023456789"}
  brokered: 409 contact_through_platform "العقار ده بوساطة المنصة: كلّمنا من الشات أو احجز معاينة."
  ```
- Edits, then `me/properties`:
  ```
  PATCH price only: 200 published, 7000.00 EGP
  PATCH title: 200 pending_review; in the public list: False
  me/properties: pending_review, allowed_actions ['archive']
  pause while pending review: 409 action_not_allowed {"allowed_actions": ["archive"]}
  ```
- The brokered search covered the demo brokered property with an agreement, its owner given a number for the test: 12 traces (two numbers in four forms each, the account's id, email and name, the agreement's name), across 11 endpoint calls as a guest and as a resident. None was found anywhere. Its advertiser: `{"is_platform": true, "name": "عاوز إيه", "avatar_url": null, "member_since": null}`.
- Screenshots (moderator):
    - المراجعة: `docs/reports/008-review-queue.jpg`;
    - a property's page: `docs/reports/008-property-page.jpg`;
    - its owner and history: `docs/reports/008-property-history.jpg`.
- **Role access** (tests):
    - المراجعة: 200 for super admins and moderators, 403 for sales and data entry;
    - العقارات: 200 for super admins, moderators and sales, 403 for data entry;
    - creating a brokered property: 200 for super admins and sales, 403 for the others.

**EXPLAIN** on the worktree's dev database with the demo rows copied to 22,432 properties (16,824 published), analyzed, then the copies deleted:

| Sort | Plan | Execution |
| --- | --- | --- |
| newest | Index Scan Backward on `properties_published_newest_index`, 21 rows read | 0.67 ms |
| price_asc | Index Scan on `properties_published_price_index`, 21 rows | 0.19 ms |
| price_desc | Index Scan Backward on `properties_published_price_index`, 21 rows | 0.14 ms |
| nearest (5 km) | BitmapAnd of a published partial index and the GiST `location` index, distance computed for the 3,112 within 5 km, top-N sort | 229 ms |
| rent, 2+ bedrooms, newest | Index Scan Backward on `properties_published_newest_index`, filter on the rest | 1.5 ms |

## Not done and follow-ups

- **Nearest at scale:** it computes the distance of everything inside the radius before sorting (OPEN_QUESTIONS 10). Fine for Sadat City; PostGIS's `<->` ordering is the change if needed.
- **Sales cannot correct a brokered listing's text** after creating it (admins do not edit residents' text, and the panel has no edit page). A typo means rejecting it, or a follow-up edit page for platform-owned listings.
- **Agreements' scans** are stored as uploaded, on the private disk: unlike photos, they are not cleaned of metadata. They are private and admin-only. Cleaning image scans (not PDFs) is a small follow-up.
- **Badges:** the sidebar badge updates when the page loads; the tab's count updates at once.
- **Tests and settings:** `Settings::fake()` did not reach the HTTP request in a feature test here, so the limits are tested over HTTP with their defaults and with faked values through the Action directly.
- Out of scope, as the brief says: text search (2.6), chat and leads (2.8), viewings (3.1), featured placements (4.1), commission screens (4.3), a map picker in the panel.
- **Housekeeping** at the start: none was due. Task 007 was done and its worktree removed. The `awez:create-admin` prompt in the main app container was left alone.

## Deploy notes

- **Migrations:**
    - `2026_11_27_100000_add_review_columns_and_public_indexes`: `changed_since_review` on properties, providers, places and used items (default false), and seven partial indexes on published properties. Quick at today's size.
    - `database/settings/2026_11_27_100100_create_real_estate_settings`: the limits' defaults.
- **New command:** `awez:platform-account` (run once). New service provider: `RealEstateServiceProvider` (route binding, the `contact` rate limiter, the listener, the options cache).
- **No new packages.**
- **New env names, all optional:** `AWEZ_CONTACT_RATE_LIMIT_PER_HOUR`, `AWEZ_PLATFORM_ACCOUNT_EMAIL`, `AWEZ_DEMO_PHOTOS`.
- **Roles:** re-seed reference data once to add the new permissions to the existing roles (the owner's steps).
- The queue worker already listens to `default,media`; nothing new to run.
