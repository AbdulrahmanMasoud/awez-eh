# Task 002: Core support

- **Branch:** `task/002-core-support`, in `.worktrees/002-core-support` (`docs/tasks/README.md`).
- **Depends on:** task 001 (merged as #1).
- **Build plan step:** 1.2. Before coding, expand step 1.2 in `docs/BUILD_PLAN.md` into **Acceptance** and **Verify** lines from this brief, as step 1.1 has them.

## Why

Every domain from step 1.3 on needs the same few building blocks: money, Egyptian phone numbers, Arabic text that searches well, one shape for errors, and the `/api/v1` group. Built once here, tested once, used everywhere.

## Housekeeping first

- Set task 001's row to `done` (PR #1), add this task's row (`002`, `Core support (BUILD_PLAN 1.2)`), and update `PROGRESS.md`.
- Remove the task 001 worktree and its local branch, after `vendor/bin/sail down -v` inside it so its containers and volumes go too. Delete the merged remote branches `task/001-project-skeleton` and `docs/free-sign-in-and-maps`.

## 1. Application defaults (`AppServiceProvider`)

- `Model::shouldBeStrict()` outside production.
- Dates are `CarbonImmutable` (`Date::use()`).
- In production: `DB::prohibitDestructiveCommands()` and `URL::forceScheme('https')`.
- `config('app.timezone')` stays `UTC`; a `config('awez.display_timezone')` of `Africa/Cairo` is what presentation code will read.

## 2. Errors

- `App\Exceptions\DomainException` (abstract): an error code (`snake_case`), a translation key, an HTTP status (422 by default), and optional context that is never shown to clients.
- `bootstrap/app.php` renders, for every request under `/api/*`, one JSON shape: `{ "message": "...", "code": "...", "errors": { ... } }`.
    - Domain exceptions: their translated message, code and status. They are not reported to the error tracker.
    - Validation: 422, code `validation_failed`, `errors` keyed by field.
    - Unauthenticated 401 `unauthenticated`, forbidden 403 `forbidden`, not found 404 `not_found` (a missing model never reveals its class or id), too many requests 429 `too_many_requests`, and anything else 500 `server_error` without a trace outside `local`.
- Messages are translated in `lang/ar` and `lang/en`.

## 3. Money

- Add `brick/money` (named in `CLAUDE.md`).
- `App\Support\Casts\MoneyCast`: a `Money` attribute stored as a `bigint` minor-units column plus a `currency` column (EGP by default, configurable per cast). It rejects floats, ints and strings, never goes through a float, and refuses to change a row's currency silently.
- Unit tests for the round trip and every rejection. Mutation testing (`pest --mutate`) on the cast, as `CLAUDE.md` asks for money.

## 4. Egyptian phone numbers

- `App\Support\PhoneNumber` (final, readonly), with no new package: Egyptian mobile numbers only for now (`010`, `011`, `012`, `015`, 11 digits locally).
- It accepts the common ways people type them: `01012345678`, `+201012345678`, `00201012345678`, `201012345678`, with spaces or dashes, and Arabic-Indic digits (`٠١٠١٢٣٤٥٦٧٨`). Anything else throws a `DomainException` (`invalid_phone_number`).
- `e164()` returns `+201012345678`; `national()` returns `01012345678`; `mask()` keeps the first 3 and last 3 digits. Casting to a string returns the masked form, so a log line cannot leak the number.
- A `PhoneNumberCast` stores the E.164 form.
- Unit tests for every accepted form and every rejected one.

## 5. Arabic text

- `App\Support\Text\ArabicNormalizer` with one public method, `normalize(string): string`, pure and without I/O:
    - `أ إ آ ٱ` → `ا`; `ة` → `ه`; `ى` → `ي`; `ؤ` → `و`; `ئ` → `ي`;
    - diacritics (tashkeel) and tatweel removed;
    - Arabic-Indic and Persian digits → Western digits;
    - whitespace collapsed and trimmed; Latin letters lowercased.
- Unit tests with real examples from the app: `صيدليّة` and `صيدليه`, `إيجار` and `ايجار`, `شقة ٣ غرف` and `شقه 3 غرف`, `مستشفى` and `مستشفي`.
- A feature test proves that after normalization, `pg_trgm` `similarity()` between a typo and the right word is above the threshold we will use for search (record the threshold in `DECISIONS.md`).

## 6. Requests and logs

- Every request gets a `request_id` in Laravel `Context`: a safe incoming `X-Request-Id` (UUID or short token, length-limited) or a new UUID. It is echoed in the response's `X-Request-Id`.
- A `json` log channel writes one line per entry carrying the context. Phone numbers, tokens and codes never appear (a test logs a `PhoneNumber` and checks the line).

## 7. Locale and `/api/v1`

- Arabic is the default locale (`APP_LOCALE=ar`, fallback `en`). A middleware on the API sets the locale from `Accept-Language` (`ar` or `en`; anything else falls back to `ar`) and answers with `Content-Language`.
- `routes/api.php` has one `v1` group (prefix `api/v1`, name `api.v1.`) and a public `GET /api/v1/ping` returning a small API Resource: `{ "data": { "status": "ok", "locale": "ar", "time": "<UTC ISO 8601>" } }`. It exists to prove the group, the locale and the resource shape; step 1.4 replaces it with real endpoints.
- Every API response is JSON, even when the client did not send `Accept: application/json`.
- Rate limiting: a named `api` limiter (per user when signed in, else per IP), applied to the group. Its numbers go in `config/awez.php`.

## 8. Architecture tests

- The rule `CLAUDE.md` lists and task 001 left for now: **controllers return API Resources** (or `JsonResource` collections, or `204` responses). Write it so it applies to every class under `App\Http\Controllers\Api`.
- Form Requests: classes in `App\Http\Requests` extend `FormRequest` and are final.
- `App\Support` does not depend on `App\Domains`, `App\Http` or `App\Filament`.
- Prove each new rule with a throwaway that breaks it (then removed; say so in the report).

## 9. CI

- Trigger on pushes to `main` and on pull requests only, as SellBubble does (owner's decision; record it in `DECISIONS.md`).

## 10. Decisions already taken by the owner (record them in `DECISIONS.md`)

- The PostGIS image running emulated on Apple silicon is accepted.
- `composer.json` keeps `"php": "^8.5"`.
- Step 1.4 may **edit** the `users` migration from task 001 instead of adding a second one, because nothing has been deployed. From the first deploy on, "never edit a merged migration" applies with no exception.

## Out of scope

Domain tables and models (1.3), sign-in (1.4), Filament (1.5), spatie/laravel-data and spatie/laravel-settings (they come with the first domain that needs them). Laravel's welcome page and frontend files stay as decided in entry 18.

## Acceptance

- `sail composer check` green; the test count and the mutation score of `MoneyCast` are in the report.
- Real run: `curl -i localhost:8020/api/v1/ping` and the same with `Accept-Language: en`, a request that triggers each error type (a throwaway route used only for the check, then removed), and a log line showing the `request_id`. Paste the outputs in the report.
- No new migration is needed; if one is, verify it on `testing` as the definition of done says.
