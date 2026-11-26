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
39. **Search leaves out words under three letters, and the trigram operator is priced at its real cost** (owner's decisions; both land with step 2.6). Measured on PostgreSQL 18 with 20,030 properties, in a transaction that was rolled back:
    - *Short words.* At 0.25, a term that begins like a two-letter word, which Arabic text is full of, matches every text that contains that word: `منزل` scores 0.333 against `من`, `فيللا` 0.286 against `في`, `علاج` 0.286 against `علي` (normalized `على`), `لابتوب` 0.25 against `لا`. Step 2.6 leaves words under three letters out of `search_text` and out of search terms, keeps the threshold at 0.25 (the swapped-letter typo, 0.27, is still found), and recomputes `search_text` for the rows that exist then.
    - *The index.* The GIN index serves the search, written either way round (PostgreSQL turns `:term <<% search_text` into `search_text %>> :term` itself). It answered in 187 ms against 2,932 ms for a sequential scan, yet the planner chose the scan, because pg_trgm prices the operator's function at cost 1 per row. After `ALTER FUNCTION strict_word_similarity_commutator_op(text, text) COST 100` the planner chose the index by itself (177 ms). We host PostgreSQL ourselves, so the migration that runs this statement in step 2.6 has the extension's owner.
40. **Opening hours are Cairo wall-clock times**, the one exception to "time is UTC" (invariant 4): a shop opens at 09:00 whatever the offset is that day.
    - `OpeningHoursCalculator` reads the moment in `awez.display_timezone`, summer time included, and compares wall-clock seconds. Those differ from seconds elapsed since midnight on the two days a year Egypt changes its clocks, which it does at midnight.
    - An interval is open from its opening time up to, not including, its closing time. A closing time earlier than the opening time is on the next day; the same time for both means 24 hours. Several rows on one weekday are split shifts, and a weekday with no rows is closed.
41. **Deleting an account closes the resident's brokered listings and keeps them**, with their agreements and their leads, and blanks the resident's personal data (owner's decision; implemented in step 1.4). The platform's commission rests on those rows. The rest of a resident's content goes with the account. This task's `properties.user_id` cascades for every property, so step 1.4 also chooses how brokered ones survive: the account row stays with its personal data blanked (nothing cascades, and the deleting Action removes the rest of the content), or brokered properties get a nullable `user_id` with `nullOnDelete`.
42. **Reference data is never deleted from under content.** Properties, providers, places, used items and requests reference cities, districts, trades and place categories with `restrictOnDelete`; admins deactivate a row (`is_active`) instead.
    - A city's districts go with the city (the brief).
    - A place category with subcategories cannot be deleted.
    - A place an admin added outlives the account that claimed it (`nullOnDelete`).
43. **Review: the districts list is incomplete.** The seeder has the seven the brief names (the first, second, fourth, seventh, eighth and eleventh residential areas, and the industrial zone), without centres. The owner's full list, with a centre for each, goes in the seeder; admins can add districts in the panel from step 1.5.
44. **Icons are Lucide names** (`wrench`, `pill`, ...; accepted by the owner), each checked against Lucide's list. Lucide is the stroke set closest to the admin panel's Heroicons; the mobile app ships it.
45. **A place's phone takes Egyptian landlines too** (owner's decision, done in this task).
    - `PhoneNumber::fromMobileOrLandline()` accepts a mobile or a landline. `PhoneNumber::from()` stays mobile-only, for residents' numbers and anything a WhatsApp message goes to. `isMobile()` tells the two apart.
    - Landlines: area code 2 (Cairo and Giza) with 8 digits; 3 (Alexandria) and 13, 40, 45–48, 50, 55, 57, 62, 64–66, 68, 69, 82, 84, 86, 88, 92, 93 and 95–97 with 7. Menoufia, Sadat City's governorate, is 48. The list is Wikipedia's "Telephone numbers in Egypt" (edited 17 November 2026), which cites no plan from NTRA: a number refused by mistake is fixed by adding its code.
    - Left out: 15, the 10th of Ramadan's old code, whose ten digits read as a WE mobile number missing a digit; five-digit hotlines (16xxx, 19xxx); and parentheses around the area code, refused as they are for mobiles.
    - `PhoneNumberCast` takes mobiles only unless its column adds `PhoneNumberCast::LANDLINES`. A place's `phone` does. Its `whatsapp`, and an agreement's `owner_phone`, stay mobile: WhatsApp Business can run on a landline, but a shop's WhatsApp is almost always a mobile, and a landline there is more often a typo.
    - Logs mask landlines written as text, as they mask mobiles.
46. **Who may see what**, at the policy level until the endpoints of phase 2 add their own tests:
    - moderated content (properties, providers, places, used items): published rows to everyone, and the owner's own rows in any status;
    - requests: open ones to everyone, unless `expires_at` has passed, even before a job marks them expired; closed and expired ones to their owner only;
    - a reply is seen only by its author and by the request's owner, who cannot change it, so competitors do not see each other's offers (owner's decision: replies stay private);
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

## Step 1.3b (task 004)

54. **The rest of the data model:** domains `Conversations`, `Bookings`, `Reviews`, `Favorites` (`favorites`), `Alerts` (`search_alerts`), `Commissions`, `Promotions` and `CityGuide`. Laravel's `notifications` table (`make:notifications-table`) uses UUID morphs, and users are stored in it as `user`; it has no model of ours, and the Notifications domain gets its notification classes in step 3.4.
55. **Append-only tables are enforced by PostgreSQL:** `leads`, `booking_events` and `commission_entries`.
    - `App\Support\Database\AppendOnly::enable()` attaches triggers that refuse `UPDATE`, `DELETE` and `TRUNCATE` (which skips row triggers); `disable()` detaches them.
    - The SQL is literal, and the table is a bound `regclass`, so PostgreSQL quotes the names. Its three functions (`append_only_refuse`, `_attach`, `_detach`) are created or replaced on every call: `migrate:fresh` drops tables and their triggers but leaves functions, and a rollback leaves them too, which is harmless.
    - The models use `IsAppendOnly`: `save()` on an existing row and `delete()` throw `App\Exceptions\AppendOnlyViolation` before the database is asked, and rows record `created_at` only.
56. **Some rows keep their parents for good,** because their tables are histories or append-only (owner's decision). These can never be hard-deleted:
    - a user who is the resident or counterpart of a booking, the actor of a booking event, the resident of a lead, the recorder of a commission entry, or the sender of a message in someone else's conversation;
    - a property with leads or commission entries, and an agreement with commission entries;
    - a booking, and a lead that a correction or a commission entry points at.

    An account with history is never hard-deleted: step 1.4's account deletion blanks its personal data instead, as entry 41 does for brokered listings (owner's decision).
57. **A message keeps its sender's account** with `noActionOnDelete`, which PostgreSQL checks at the end of the statement. A conversation deleted with its resident takes its messages with it, but a member of staff whose messages stay in residents' conversations cannot be deleted; staff accounts are deactivated instead.
58. **Conversations.**
    - No counterpart means the platform answers, as on every brokered property: the owner's id is in neither the conversation nor its messages, which a test checks, and the owner cannot read them.
    - A resident has one open conversation per subject (a partial unique index); the subject is one of `property`, `provider`, `place`, `used_item` or `request_reply` (a `CHECK`).
    - A conversation goes with either participant's account. A brokered one's resident has leads, so that account cannot go (entry 56).
    - Only the participants see a conversation; nobody deletes one through the API, because a lead may point at it.
59. **Leads.** One live lead per conversation or booking (a partial unique index).
    - A correction is a new row that carries the voided lead's details and source, with `voids_lead_id` and a required `void_reason`. A lead is voided at most once.
    - A chat lead comes from a conversation, and a viewing lead from a booking (a `CHECK`).
    - No resident reads a lead through the API, not even the one it is about (`LeadPolicy` refuses everyone); admins see leads in Filament.
60. **The booking state machine** (`BookingStateMachine`; approved by the owner as proposed). The brief names the statuses and the actors; the transitions:

    | From | To | Kinds | Who |
    | --- | --- | --- | --- |
    | requested | confirmed | both | counterpart, platform |
    | requested | declined | both | counterpart, platform, system (nobody answered in time) |
    | requested, confirmed | cancelled_by_resident | both | resident |
    | confirmed | completed | viewings | counterpart, platform |
    | confirmed | on_the_way, in_progress | visits | counterpart |
    | confirmed | cancelled_by_counterpart, no_show | both | counterpart, platform |
    | on_the_way | in_progress, no_show | visits | counterpart |
    | on_the_way | cancelled_by_resident | visits | resident |
    | on_the_way | cancelled_by_counterpart | visits | counterpart, platform |
    | in_progress | completed | visits | counterpart |

    - "Platform" is the staff, who run brokered viewings; `no_show` means the resident did not show up.
    - A visit completes only from `in_progress`. Declined, completed, both cancellations and `no_show` are terminal.
    - The database also refuses `on_the_way` and `in_progress` for a viewing, and a viewing of anything but a property or a visit by anyone but a provider.
    - Rescheduling is not a status: it is an edit of `scheduled_at` with its own booking event, built in step 3.1 (owner's decision).
    - The table is built from `BookingTransition` objects in a method, not a constant: mutation testing runs only the tests that cover a line, and line coverage never reaches the inside of a constant.
61. **Reviews:** one per booking (a unique `booking_id`); a rating from 1 to 5; a reply comes with its date; `is_hidden` comes with `hidden_at`. That the booking is `completed` is the Action's check in step 3.2. Everyone sees a review unless an admin hid it, and its reviewer always does.
62. **`CommissionBalance`.**
    - `expected` is the sum of `expected` entries minus `cancelled` ones; `invoiced` and `collected` are their own sums; `outstanding` is invoiced minus collected.
    - Amounts are positive (a `CHECK`), and all of a property's entries are in one currency: Money refuses to add another.
    - Pest makes only two mutations of it, because its operators do not swap Money's `plus()` and `minus()`; the unit tests check every total.
63. **Search alerts** keep their query and its normalized `search_text`, which stays null without a query. A saving hook sets it, not a mutator: an attribute named `query` cannot have one, because Eloquent's static `query()` takes that name. `filters` is `jsonb`, `'{}'` until step 3.4 shapes it. A point and a radius come together. `search_text` has its GIN index, like every `search_text` column.
64. **Featured placements** are active from `starts_at` up to, not including, `ends_at`, unless cancelled (`active()`). Their morph columns are written by hand, so the brief's index on `(featurable_type, featurable_id, ends_at)` replaces the one `uuidMorphs` would add. One with no package was granted by an admin.
65. **Polymorphic columns list their short names in a `CHECK`**: what a conversation, a booking, a review, a favorite, a placement or a lead may point at. A wrong but mapped type, such as a favorite of a report, is refused.
66. **City guide.**
    - `phone_display` is text: emergency lines have three digits (`122`) and hotlines five, which `PhoneNumber` rightly refuses.
    - A duty pharmacy's `duty_date` is a Cairo calendar date, like opening hours (entry 40), while `starts_at` and `ends_at` are UTC.
    - A news item's author is kept with `nullOnDelete`, so an admin account can go, and a published item has a date.
67. **The emergency numbers** seeded for Sadat City are Egypt's national lines (kept by the owner): الشرطة 122، الإسعاف 123، المطافئ 180، طوارئ الكهرباء 121، طوارئ الغاز 129، طوارئ المياه 125. The city's hospitals and transport lines are still to come (OPEN_QUESTIONS 5).
68. **The morph map adds `conversation`, `message`, `booking` and `review`.** Messages and reviews can be reported.
69. **Demo interactions** (`DemoInteractionSeeder`, after `DemoContentSeeder`, in `local` and `testing` only).
    - A member of staff (`staff@awez-eh.test`) answers for the platform; admin roles come in step 1.5.
    - Every booking's history is walked through the state machine.
    - Every brokered conversation and viewing has its lead, and one chat lead is corrected.
    - The commission entries follow the agreement's rate on a deal of 1,800,000 pounds.
    - The two featured packages have demo prices (OPEN_QUESTIONS 2).
    - A pharmacy is on duty for each of the next 14 nights, 20:00 to 08:00 Cairo time.
70. **Two Rector rules don't fit Laravel code here:**
    - `RemoveUnusedPublicMethodParameterRector` is skipped for policies. The Gate reads a policy method's first parameter to decide whether a guest may be asked at all, so its parameters are a contract even when the answer ignores them.
    - A booking's events are `timeline()`, not `events()`: Rector reads `->events` as Eloquent's old `$events` property and renames it to `dispatchesEvents`.

## Step 1.4 (task 005)

71. **The `log` phone driver writes codes to a log: the one exception to "never log a code"** (invariant 8).
    - The code goes to its own channel, `phone_verification` (`storage/logs/phone-verification.log`). That channel is not redacted, and nothing else writes there. The number in the line is masked (`010*****678`).
    - No other channel ever receives the code; a test reads every log file to check.
    - Outside `local` and `testing`, this driver and the fake sign-in (`AWEZ_SIGN_IN_FAKE`) are refused (`IdentityServiceProvider`). The app serving requests refuses to boot with either, and anything else that resolves a driver, such as a queue worker or a command, throws when it does. Console commands that touch neither still run: Composer's `package:discover` boots the app during `composer install`, before CI or an image build has a `.env`, so the environment is `production` then. Production therefore needs `AWEZ_PHONE_VERIFICATION_DRIVER=whatsapp_inbound`, which step 4.3b builds.
72. **Deleting an account anonymizes it, and the row always stays** (DECISIONS 41 and 56; the choices beyond the brief below approved by the owner). This settles entry 41's two options. `DeleteAccount` does it all in one transaction, or none of it.
    - **Identity:** tokens, device tokens, social accounts and phone verifications are deleted.
    - **Personal data:** `name` becomes "حساب محذوف", in the default locale, because the name is stored data. `email`, `phone`, `avatar_url`, `password`, `district_id` and their dates are blanked. `status` becomes `deleted`, and `deleted_at` is set.
    - **Each domain cleans up its own rows** through its own Action:
        - `RemoveAccountProperties`: brokered properties are archived, with their agreements and leads.
        - `RemoveAccountUsedItems`: the brief deletes used items. A used item that something points at is archived instead, so no conversation, favorite, placement or report is left pointing at nothing.
        - `ArchiveAccountProvider`: the provider profile is archived.
        - `ReleaseAccountPlaces`: places lose their owner and stay as they are.
        - `RemoveAccountRequests`: requests without replies are deleted, and open requests with replies are closed.
        - `DeleteAccountFavorites`, `DeleteAccountSearchAlerts` and `WithdrawAccountReports`: favorites, alerts and open reports are deleted. Resolved and dismissed reports stay, as the record of what admins decided.
    - **Content the account left with others stays,** under the anonymous name: conversations, messages, bookings, reviews, leads, and replies it wrote to other residents' requests. Featured placements it bought stay too, since they are payment history.
    - **"Points at"** means any agreement, lead, commission entry, conversation, booking, favorite, featured placement or report. Polymorphic columns have no foreign key, so the Action asks each relationship (`isPointedAt()` on `Property` and `UsedItem`).
    - **Signing in again** with the same Google or Apple account creates a new user, because the account's social links were deleted.
    - **Not handled yet:** the account's database notifications. The Notifications domain gets its Actions in step 3.4.
73. **Finding the account at sign-in** (approved by the owner).
    - First by `(provider, subject)`.
    - Else by email, lowercased, and only when the provider says it is verified. An unverified email is never linked and never stored.
    - A suspended account found by its email refuses the sign-in (`account_suspended`), so a second provider cannot get around a suspension.
    - If the account with that email already has another account of this provider, a new user is created without the email, because emails are unique.
    - A new user's name is the one the app sends (Apple), else the token's, else "مستخدم أوز إيه".
74. **Tokens.**
    - There is one Sanctum token per device name: signing in again under the same name deletes the old token, and its push token with it.
    - Tokens never expire (owner's decision): `sanctum.expiration` stays null, Sanctum's default. A token ends on sign-out and on account deletion.
    - The platform is stored on the push token only (owner's decision): `PUT me/device-token` carries it, and sign-in takes none.
75. **ID tokens** (`IdTokenDecoder`, used by the Google and Apple drivers).
    - Only RS256 is accepted. `exp` and `iat` are required, with a 60-second leeway. php-jwt checks `nbf` too.
    - `aud` must be one of the configured client ids. While none are configured, every token is refused.
    - The keys are cached under the URL's hash, for their `max-age`.
    - The token's `kid` is read before the signature is checked. An unknown one refreshes the keys once. A key URL that cannot be reached refuses the token (401), because a 500 would only make the app retry.
76. **Phone confirmation details.**
    - The code's hash is an HMAC-SHA256 keyed by `APP_KEY`. A six-digit code hashed without a key is reversed in moments by anyone who reads the table. Rotating `APP_KEY` only invalidates the open codes.
    - The rate limits count `phone_verifications` rows over sliding windows (`RateLimit`, a pure class), so a replaced start still counts. `Retry-After` is the largest wait among the limits reached.
    - Starts are serialized per user with a row lock. A wrong attempt is counted and committed before the error is thrown, so a rollback cannot hand the attempt back.
    - `PhoneVerifier::start()` also takes the IP address, which the driver stores, and drivers say their `driver()`; the brief's signature had neither.
    - Known limit: deleting an account deletes its rows, and with them its counts. Getting around the limits that way needs a new Google or Apple sign-in each time.
77. **The `users` table** edits task 001's migration (DECISIONS 21).
    - `district_id` comes in the Identity migration, because districts are created after users.
    - `email` and `phone` are unique where not null (partial indexes), and emails are stored lowercased.
    - `CHECK`s pair each state with its date: a phone with `phone_verified_at`, `suspended` with `suspended_at`, `deleted` with `deleted_at`.
78. **Push tokens.**
    - There is one row per access token. `PUT me/device-token` answers 201 when it creates the row and 200 when it replaces it.
    - A push token names one app install, so if another access token held it (the phone signed in again, or changed hands), that row goes. Pushes then reach only the device's current account.
79. **Domain errors can carry `errors` and headers.** `DomainException::errors()` is how `verification_code_invalid` sends `attempts_left`, and `headers()` is how `too_many_attempts` sends `Retry-After`.
80. **A guest's request to a signed-in route is not counted by the `api` limiter:** Laravel's middleware priority runs `auth:sanctum` first, which answers 401 without doing any work. Public routes (sign-in) are counted per IP.
81. **Data objects are `spatie/laravel-data` classes** (owner's decision): `VerifiedIdentity`, `PhoneChallenge`, `ProfileChanges`, `SignedIn` and `IssuedPhoneCode` extend `Data`, and an arch rule keeps every domain's `Data` layer that way.
    - They are `final` with `readonly` properties, not `final readonly` classes: `Data` keeps a mutable context, and PHP allows a readonly class to extend only a readonly one.
    - `ProfileChanges` uses `Optional` for the fields `PATCH me` leaves out, and `UpdateProfileRequest::toData()` builds it with `from()`, mapping snake_case input to its properties.
    - `IdTokenClaims` moved to `Drivers` and stays a plain class: it is the decoder's view of a token, and nothing crosses a boundary with it.
82. **Seeing a user is not changing them:** `last_seen_at` is written through the query builder, so `updated_at` stays.
83. **`UserFactory` reads each user back after creating it,** so it has every column, as one loaded by a request does. Strict models throw on a column the factory did not set.
84. **The Identity tables' policies deny everything:** the rows are reached only through the token's own account (`me`), never by id.
85. **No config file may need a development package.** Laravel loads every file in `config/` at boot, and a production install (`--no-dev`) has no Scribe. `config/scribe.php` returns `[]` when Scribe's classes are missing. `BootWithoutDevPackagesTest` boots the app in a child process with Scribe's classes and service provider hidden, as `--no-dev` leaves it.

## Step 1.5 (task 006)

86. **Admin permissions count only inside the panel, and sit in the same policies as the resident rules** (ADR 0006).
    - A policy asks `$user->mayAdminister('Update:City')` beside its resident rules. That is true only when the request is inside the admin panel (`App\Support\AdminArea`, which the panel provider points at Filament's current panel), the account is active, and one of its roles holds the permission.
    - Through the API, an admin is a resident like any other. An admin who also signs in to the app with Google gets nothing from their roles there: no update and no view that a resident would not get. API lists are scoped queries, not `viewAny` checks, and every ability needs the panel.
    - Residents hold no role and no permission.
    - Spatie's permission gate (an ability named like a permission) stays on, but no policy ability is named like one (`update` against `Update:City`).
    - `super_admin` holds every permission explicitly (`SyncAdminRoles`). Shield's gate shortcut (`define_via_gate`) is off, because it would grant every ability everywhere, the API included.
    - Shield's generated policies are off: policies stay one per model, written by hand. Shield names the permissions (`Affix:Subject`, PascalCase), and its roles screen lists them; a test keeps `AdminPermissions::all()` equal to Shield's catalogue.
    - The panel runs with `strictAuthorization()`: a missing policy method is an error, not "allowed".
87. **Roles and permissions** (spatie/laravel-permission): `super_admin`, `moderator`, `data_entry` and `sales` (ARCHITECTURE §4), seeded everywhere by `ReferenceDataSeeder` through `SyncAdminRoles`.
    - Seeding is idempotent and only adds, so permissions a super_admin gives a role on the roles screen stay.
    - This step's defaults: `super_admin` everything; `moderator` users (view, suspend, reactivate, show a number); `data_entry` cities, districts, trades and place categories (view, create, edit, reorder); `sales` users (view).
    - The four roles cannot be deleted on the roles screen; a role added there can.
    - The tables come from the package's stub, with `model_id` a UUID, an index on `role_has_permissions.role_id`, and no teams. Roles and permissions keep the package's integer ids, like framework tables (DECISIONS 9).
88. **Who enters the panel:** a user with an admin role, a password and an active account (`User::canAccessPanel()`); anyone else gets 403.
    - Two-factor authentication with an authenticator app (Filament's, with recovery codes) is required: an admin without it is sent to set it up before any page. Its secret and recovery codes are stored encrypted, in two new `users` columns.
    - Filament limits sign-in attempts to 5 a minute per IP address, and the two-factor challenge separately per user.
    - An admin suspended or deleted while signed in is signed out on the next request (`AuthenticateAdmin`, which extends Filament's `Authenticate` so Laravel's middleware priority keeps it first).
    - Suspending an account also deletes its web sessions, as well as its API tokens.
    - Deleting an account through the API removes its roles and authenticator, and refuses the last active super_admin (`last_super_admin`, 409), who also cannot be suspended or lose the role.
89. **`awez:create-admin`** creates an admin, or promotes the account with that email, keeping its name. It is the only way to make the first super_admin, in every environment.
    - The password is asked twice, hidden, and never taken as an option, so it stays out of shell history. `Password::defaults()` is at least 12 characters.
    - It prints the email and the role, nothing secret, and refuses a suspended or deleted account.
    - The only admin with a known password is `DemoAdminSeeder`'s `admin@awez.test`, in local and testing only.
90. **The activity log** (spatie/laravel-activitylog, log name `admin`) records admin actions only.
    - Reference data (cities, districts, trades, place categories) logs its fillable fields, before and after, when changed inside the panel (`LogsAdminChanges`); seeders, the API and jobs are not admin actions and are left out. Locations are left out too.
    - Users are never logged by model events. Their Actions write one entry each, with the admin, the reason, and before and after for statuses and roles: `SuspendUser`, `ReactivateUser`, `UpdateUserRoles`, `RecordPhoneReveal` (who looked at a number, never the number) and `CreateAdmin`. A test proves no name, email or number reaches the log.
    - The table uses UUID morphs and `jsonb`, indexed by subject, causer, event and time.
    - The read-only page (`سجل النشاط`) is for `View:ActivityLog`, which is `super_admin`'s by default.
91. **The panel's look and language** (docs/design/ADMIN_PANEL.md).
    - The theme is Filament's own (`make:filament-theme`), built by Vite inside Sail and in CI. The brand colours are the panel's colour map, and the theme file adds the background, the radii and the dark stat card.
    - Readex Pro is self-hosted (entry 96).
    - The language is the admin's `users.locale` (`SetAdminLocale`), Arabic before signing in, switched from the user menu; Filament's Arabic translations make the layout right to left.
    - The navigation groups are an enum in the design's order. Reference data, users, roles and the activity log sit under النظام, the only group with entries yet (approved by the owner).
    - Lucide icons are drawn from `mallardduck/blade-lucide-icons` (owner's approval). Reference data picks from `App\Support\LucideIcons`, and every name there is a real Lucide icon (checked against the package's 2,416).
92. **Filament's notification bell reads `notifications.data` as JSON**, and Laravel's table stored it as text; a migration makes it `jsonb`.
93. **The users list** shows numbers masked; the full number appears only on demand, for roles allowed it, and the log records each time.
    - Search: a number typed any way finds its account through the unique index; otherwise the search is by email (exact) or part of the name. The name search scans the table, which is fine at this size; a trigram index can come when it is not. Search terms are not split, so `0101 234 5678` is one number.
    - "Joined" sorts by `id` (UUID v7, DECISIONS 53). Status has a new index for its filter; the other filters ask indexed foreign keys.
94. **The dashboard** has the three widgets whose data exists: the review queue, listings published by kind, and new users with the share that confirmed a phone.
    - The period is today (from midnight in Cairo), the last 7 days or the last 30: rolling windows, so a period compares with one of the same length (approved by the owner).
    - The review queue is the whole queue, whatever the period: a queue filtered by date would hide its oldest items, which are the ones that matter (approved by the owner).
95. **Only two panel middleware are persistent** (re-run on Livewire's own requests): `SetAdminLocale` and `AuthenticateAdmin`; Filament makes its own persistent. Making the whole list persistent ran `StartSession` and `EncryptCookies` a second time inside every Livewire request. That replaced the session and signed the admin out on the next request: the real run found it when the notification bell lazy-loaded. A test pins the persistent list.
96. **Readex Pro is self-hosted** (owner's decision): no font CDN at run time.
    - Laravel's Vite plugin downloads it at build time (Bunny's files, weights 300 to 700, the Arabic and Latin subsets, each with its unicode range) into `public/build`.
    - The panel uses Filament's local font provider and prints the build's font faces in its head (`Vite::fonts('readex-pro')`).
    - The build needs the network; a page never does. A test checks the sign-in page names no font host and loads the build's files.
97. **Western digits (0-9) everywhere in the admin panel**, numbers, money and dates included, with the text in Arabic (owner's decision; the mobile app's choice stays open, OPEN_QUESTIONS 7).
    - For Arabic the number locale is ICU's `ar-u-nu-latn`, Arabic with the Latin numbering system: `1,234,567.5` and `42,500.00 ج.م.`. English stays `en`.
    - It is set in one place (`App\Support\AdminNumbers`): `SetAdminLocale` gives it to Laravel's `Number`, and the panel provider gives it to Filament's tables and schemas, read per request, since each admin has their own language.
    - Carbon already writes Western digits in Arabic dates (`25 نوفمبر 2026`).
    - Tests render the panel's pages in Arabic and find no Arabic-Indic digit.

## Step 2.1 (task 007)

98. **Packages** (owner's approval): `spatie/laravel-medialibrary` 11.23.14 (with `spatie/image` 3.9.10), `league/flysystem-aws-s3-v3` 3.35.3 (with `aws/aws-sdk-php`), `spatie/laravel-settings` 3.10.2, and Filament's `filament/spatie-laravel-media-library-plugin` 5.12.0 for the panel's image field and column.
99. **Where files go** (ADR 0007).
    - Every collection takes its disks from `config('awez.media.disk')` and `config('awez.media.private_disk')` (`MEDIA_DISK`, default `public`; `MEDIA_PRIVATE_DISK`, default `local`); no code names a disk (an arch test reads `app/`). R2 is two disks on the `s3` driver, `r2` (public bucket, its public domain) and `r2_private` (no URL), used once `.env` names them.
    - **A photo's cleaned original is on the private disk; only its two sizes are public.** The brief put photos on the public disk; the real run showed that a size's URL gives away the original's (`.../conversions/photo-thumb.webp` → `.../photo.jpg`), which answered 200. Now it answers 403, and "public URLs only for the two sizes" holds for anyone, not only for the API.
    - Agreements' documents are private entirely: no size, no URL. An admin holding `ViewDocuments:PropertyAgreement` (`super_admin` by default; a Shield custom permission until the agreements' screen in step 2.2) gets a signed URL for 5 minutes, made when they click, and each one writes `viewed_private_media` in the activity log.
100. **Cleaning before storing** (`App\Domains\Media\Images\ImageCleaner`, Imagick).
    - The type is read from the content (finfo); JPEG, PNG and WebP are accepted. ImageMagick is told the decoder (`JPEG:path`), so it never chooses one from the file (its SVG, PostScript and MSL decoders are never reached), and reads the first frame only.
    - **HEIC is refused**: Sail's ImageMagick lists HEIC, but its libheif has no HEVC decoder (`Unsupported codec`), so a phone's HEIC cannot be read. The app sends JPEG (OPEN_QUESTIONS 8).
    - The header is read first and a canvas over 120 megapixels is refused before decoding (a small file can declare a huge canvas). A JPEG larger than the cap is decoded at a reduced scale (`jpeg:size`); below the cap the hint is not set, since libjpeg would enlarge.
    - Then: upright from the EXIF orientation, every profile and property stripped except the colour profile (so a phone's wide-gamut photo keeps its colours), CMYK to sRGB, transparency onto white, at most 2560 px on the long side, written as a progressive JPEG at quality 85 named `photo.jpg`. The uploaded bytes and the client's file name are never stored.
    - The Filament plugin's own upload stores the file as it comes; the panel's field saves through the same `AddImage` instead.
101. **Two WebP sizes**: `thumb` 480 px wide and `large` 1600 px wide, quality 80, `Fit::Max` (never enlarged), made by Medialibrary's own job on the `media` queue after the commit. Running it twice writes the same files (tested). `composer dev`'s worker listens to `default,media`; production's must too. The API answers `processing: true` and null URLs until both exist.
102. **Pending uploads**: the app uploads photos before the listing exists. A pending upload is its uploader's alone for 24 hours; anyone else's, or an expired one, is not found (404, the same as a missing id). `AttachUploads` hands the media rows to the listing in the order given, after what the collection holds, within its limit, all or nothing, under row locks; the files stay where they are (each media row has its own folder), so nothing is copied in the transaction and the sizes already made are kept. An hourly job (`ShouldBeUnique`, on the `media` queue) deletes expired uploads with their files.
103. **The API's shape.** A photo is `id`, `thumb_url`, `large_url`, `width`, `height` and `processing`; an upload adds `expires_at` and carries the upload's id. The account's `avatar_url` is its uploaded picture's small size, else the Google or Apple picture. Uploads and avatars share a budget per account per hour (`MediaSettings`, 60). `PUT /me/avatar` accepts a real multipart PUT (Symfony parses it with `request_parse_body`); Scribe documents it as a POST with `_method=PUT`, which Laravel accepts too.
104. **Settings pattern** (`spatie/laravel-settings`, first use).
    - A settings class lives in its domain's `Settings/` folder and is listed in `config/settings.php` (no discovery). Its defaults are a migration in `database/settings`, run by `migrate` with the others; it never runs again, so it never undoes an admin's change.
    - Each group has its own panel page behind a `View:<Page>` permission (`super_admin` by default) and saves through an Action that writes the changed values, before and after, in the activity log (`AdminLog::recordSettings`, with the group in place of a subject).
    - Tests change settings with `Settings::fake()`, never by writing the table.
    - The first group is `MediaSettings` (إعدادات الصور under النظام): the file size (10 MB), uploads per hour (60), and the count of each collection that holds more than one file. A collection of one (an avatar, a logo) is one by nature, not a setting. Collections and their limits are declared on each model (`InteractsWithMediaCollections`), and `media` and the new owners are in the morph map.
105. **Deleting an account** deletes its uploaded picture and its pending uploads, with their files (`DeleteAccountMedia`). Listings' photos go with the listings: Medialibrary deletes a model's media when the model is deleted; an archived listing keeps them.
