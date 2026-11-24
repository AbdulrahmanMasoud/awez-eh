# Report 002: Core support

- PR: #3 (https://github.com/AbdulrahmanMasoud/awez-eh/pull/3). CI: see the PR (it runs on the pull request only now).
- Branch: task/002-core-support. Commit: 990ff9d (the last before this report).

## What changed

- **The API answers in Arabic by default, or in English** when the app sends `Accept-Language: en`. Every answer says which language it used (`Content-Language`) and is JSON.
- **Every error the app can receive has one shape:** a translated `message`, a stable `code` the app can switch on, and `errors` by field. Nothing internal reaches a resident: not the model name, not an id, not an exception message, and not a trace outside the local environment.
- **Money is stored exactly**, in piasters, with its currency. Code that tries to store a number instead of an amount of money, change a row's currency without saying so, or round a value fails at once instead.
- **Phone numbers are read the way people type them:** spaces, dashes, `+20`, `0020`, Arabic-Indic digits, even the invisible marks a number copied from WhatsApp carries. Logs and error messages only ever show them masked (`010*****678`).
- **Arabic search will forgive typos:** text is normalized (`صيدليّة` and `صيدليه` are the same word), and the trigram threshold of 0.25 is measured against real typos.
- **Every request has an id**, in the response and in every log line it writes, so a resident's report can be traced. A JSON log is written next to the usual one. No phone number, token or code reaches any log.
- **Rate limits:** 60 requests a minute per resident, and 300 per IP address for guests, because many Egyptian mobile users share one address.
- **CI now runs once per push** to a pull request, not twice.

## Decisions for the owner

1. **The field messages of a validation error are English, even in Arabic** (`DECISIONS.md` 32, `docs/OPEN_QUESTIONS.md` 3). The `message` and `code` are translated; the per-field texts come from Laravel, which ships no Arabic.
    - Done meanwhile: Laravel's English messages.
    - Option A: a translation package such as `laravel-lang/lang`, which needs your yes for a new package. It brings Arabic for every rule and stays maintained.
    - Option B: our own `lang/ar/validation.php`, about 100 lines that we write and keep up with new rules.
    - The app shows these texts under each field, so this matters before step 2.2.
2. **The guest rate limit is 300 requests a minute per IP address**, the per-user limit 60 (`DECISIONS.md` 26).
    - A low per-IP limit would block residents who share a carrier address; a high one gives less protection from one abusive client.
    - Both numbers are `.env` settings, so they can change without a deploy of code.
3. **The trigram threshold is 0.25**, below pg_trgm's 0.3 (`DECISIONS.md` 23).
    - At 0.3, `مسشتفى` (two letters swapped) would not find `مستشفى`.
    - Unrelated words measured at most 0.09, so there is room. Step 2.6 will show whether longer texts need a different value.

## The owner's steps

1. **Delete the merged remote branches** `task/001-project-skeleton` and `docs/free-sign-in-and-maps`, and their local branches.
    - The permission check refused this when I tried; nothing was deleted.
    - From the main checkout:
        - `git push origin --delete task/001-project-skeleton docs/free-sign-in-and-maps`
        - `git branch -D task/001-project-skeleton docs/free-sign-in-and-maps`
    - `-D` because squash merges leave the branches unmerged by ancestry; both PRs show as merged on GitHub.
    - The task 001 worktree, its containers and volumes, and the docs worktree are already removed.
2. **After merging,** in the main checkout:
    - `git pull`;
    - in your own `.env`: `APP_LOCALE=ar` and `LOG_STACK=single,json` (both are in `.env.example`).

No DNS, Dokploy or production settings yet.

## Verification

**Tests first.** Before any code, the new tests ran red.

- The first run stopped while loading: a test extends `App\Exceptions\DomainException`, which did not exist yet.
- After adding that one base class: 185 tests, 27 passed (task 001's, and the new rules that already held), 158 failing across every new area.

**`sail composer check`:** Pint passed, Rector made 0 changes, Larastan at max found 0 errors with no baseline and no ignores. Pest: **190 tests, 481 assertions**, in parallel.

Tests added:

- **Unit:**
    - `ArabicNormalizer`: 29;
    - `PhoneNumber`: 39;
    - `PhoneNumberCast`: 6;
    - `MoneyCast`: 23.
- **Feature:**
    - application defaults: 11;
    - API error shape: 14;
    - locale: 12;
    - ping and rate limits: 7;
    - request ids: 10;
    - JSON log and redaction: 4;
    - trigram threshold: 10;
    - money through a PostgreSQL `bigint`: 1, using a table created inside the test's transaction, so no migration.
- **Arch:** API controllers return resources (by reflection, with a 204 `Response` allowed), Form Requests are final, and `App\Support` doesn't use `App\Domains`, `App\Http` or `App\Filament`.

**Mutation testing of `MoneyCast`.** `composer test:mutate` gives **37/37, score 100%.**

- The first run scored 86.49% (32/37). One survivor was a real gap: `currencyOf($attributes) ?? default` reduced to the default survived. No test read a row stored in another currency, so a USD row could have been read as EGP without failing.
- I added that test, and made the error-message test exact. The score is now 100%.

**New arch rules catch violations.** Each throwaway was added, run, then removed (Arch suite green again, 13/13). An action returning a 204 `Response` was also checked and passes.

| Throwaway | Failed |
| --- | --- |
| `Api\V1\ThrowawayController::__invoke(): array` | "every API controller action returns an API resource or a 204 response" |
| a non-final `App\Http\Requests\ThrowawayRequest` | "form requests are final form requests" |
| `App\Support\Throwaway` using `App\Http\ApiRequest` | "support code depends on no domain, HTTP or admin code", and the laravel preset |

**Trigram similarity, measured after normalization** (PostgreSQL 18, pg_trgm):

| Pair | Score |
| --- | --- |
| typos: `مسشتفى`/`مستشفى` 0.27, `شقة`/`شقق` 0.33, `مطعم`/`مطاعم` 0.38, `سباق`/`سباك` 0.43, `صيدلة`/`صيدلية` 0.44, `كهرباائي`/`كهربائي` 0.70 | 0.27 – 1.0 |
| unrelated: `صيدلية`/`مطعم` 0, `سباك`/`كهربائي` 0, `شقة`/`سيارة` 0, `صيدلية`/`صالون` 0.08, `مطعم`/`مستشفى` 0.09 | 0 – 0.09 |

**Real run** on the Sail stack (http://localhost:8020).

- The error cases went through throwaway routes in `routes/throwaway.php`, which were removed afterwards; that path now returns 404.
- `Date`, `Connection`, `Host`, `X-Powered-By`, `Cache-Control`, `Vary` and CORS headers are left out of the outputs. The rate-limit headers are shown on the 429 only.

```text
$ curl -si localhost:8020/api/v1/ping
HTTP/1.1 200 OK
Content-Type: application/json
Access-Control-Allow-Origin: *
Content-Language: ar
X-Request-Id: 695a1d84-9959-4e83-bea6-4271316750be

{"data":{"status":"ok","locale":"ar","time":"2026-11-24T16:16:16Z"}}
$ curl -si -H Accept-Language: en localhost:8020/api/v1/ping
HTTP/1.1 200 OK
Content-Type: application/json
Access-Control-Allow-Origin: *
Content-Language: en
X-Request-Id: 149efae4-c6d5-4737-9176-e8ce33ab6a2e

{"data":{"status":"ok","locale":"en","time":"2026-11-24T16:16:16Z"}}
$ curl -si localhost:8020/api/throwaway/domain
HTTP/1.1 422 Unprocessable Content
Content-Type: application/json
Content-Language: ar
X-Request-Id: fe9308e7-7f10-440b-be2d-37d5c4c5929c

{"message":"رقم الموبايل غير صحيح. اكتب رقمًا مصريًا مثل 01012345678.","code":"invalid_phone_number","errors":{}}
$ curl -si -H Accept-Language: en localhost:8020/api/throwaway/domain
HTTP/1.1 422 Unprocessable Content
Content-Type: application/json
Content-Language: en
X-Request-Id: 78dc2a69-8325-46d4-be72-214d0f3654d5

{"message":"This is not a valid Egyptian mobile number. Enter one like 01012345678.","code":"invalid_phone_number","errors":{}}
$ curl -si -X POST -H Content-Type: application/json -d {"age":"old"} localhost:8020/api/throwaway/validation
HTTP/1.1 422 Unprocessable Content
Content-Type: application/json
Content-Language: ar
X-Request-Id: fdf9e7d1-bc80-403f-9df1-3a49a5e55829

{"message":"بعض البيانات المرسلة غير صحيحة.","code":"validation_failed","errors":{"name":["The name field is required."],"age":["The age field must be an integer."]}}
$ curl -si localhost:8020/api/throwaway/unauthenticated
HTTP/1.1 401 Unauthorized
Content-Type: application/json
Content-Language: ar
X-Request-Id: a50956a7-37e5-4830-a339-8e51cad235d4

{"message":"سجّل الدخول أولًا.","code":"unauthenticated","errors":{}}
$ curl -si localhost:8020/api/throwaway/forbidden
HTTP/1.1 403 Forbidden
Content-Type: application/json
Content-Language: ar
X-Request-Id: 28e3207b-cef4-4ea2-a027-1b312596e563

{"message":"غير مسموح لك بهذا الإجراء.","code":"forbidden","errors":{}}
$ curl -si localhost:8020/api/throwaway/missing-model
HTTP/1.1 404 Not Found
Content-Type: application/json
Content-Language: ar
X-Request-Id: 198f0d1e-ec40-4897-a656-8354a66c6eff

{"message":"لم نجد ما تبحث عنه.","code":"not_found","errors":{}}
$ curl -si localhost:8020/api/v1/nowhere
HTTP/1.0 404 Not Found
Content-Type: application/json
Content-Language: ar
X-Request-Id: 8e333151-9c3f-453f-b2c7-6563fffce657

{"message":"لم نجد ما تبحث عنه.","code":"not_found","errors":{}}
$ curl -si -X DELETE localhost:8020/api/v1/ping
HTTP/1.0 405 Method Not Allowed
Content-Type: application/json
Allow: GET, HEAD
Content-Language: ar
X-Request-Id: 2978babf-7e23-4ce7-910e-5c74a3d54baa

{"message":"تعذّر تنفيذ الطلب.","code":"method_not_allowed","errors":{}}
$ curl -si localhost:8020/api/throwaway/throttled
HTTP/1.1 429 Too Many Requests
Content-Type: application/json
X-RateLimit-Limit: 1
X-RateLimit-Remaining: 0
Retry-After: 60
X-RateLimit-Reset: 1795537062
Content-Language: ar
X-Request-Id: 70c4561c-3602-492e-a96c-6283be0015ed

{"message":"طلبات كثيرة جدًا، حاول مرة أخرى بعد قليل.","code":"too_many_requests","errors":{}}

--- crash in local (APP_ENV=local): the debug block, trimmed to its first 2 trace frames
$ curl -si localhost:8020/api/throwaway/crash
HTTP/1.1 500 Internal Server Error
Content-Type: application/json
Content-Language: ar
X-Request-Id: 16048f30-eab3-456a-b627-319ab6cee5ed

{
  "message": "حدث خطأ من جهتنا، حاول مرة أخرى.",
  "code": "server_error",
  "errors": {},
  "debug": {
    "exception": "RuntimeException",
    "message": "The database password is hunter2.",
    "file": "/var/www/html/routes/throwaway.php:19",
    "trace": [
      "/var/www/html/vendor/laravel/framework/src/Illuminate/Routing/CallableDispatcher.php:39",
      "/var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Route.php:254",
      "... 18 more"
    ]
  }
}

--- crash on a second server in the container started with APP_ENV=staging (APP_DEBUG=true in .env)
$ curl -si 127.0.0.1:8099/api/throwaway/crash
HTTP/1.1 500 Internal Server Error
Content-Type: application/json
Content-Language: ar
X-Request-Id: 08face3f-78f8-4632-917a-4d442ca04540

{"message":"حدث خطأ من جهتنا، حاول مرة أخرى.","code":"server_error","errors":{}}
$ curl -si -H 'X-Request-Id: real-run-002' localhost:8020/api/throwaway/log
HTTP/1.1 204 No Content
X-Request-Id: real-run-002
$ tail -1 storage/logs/laravel.json
{"message":"Code sent to 010*****678","context":{"phone":"015*****678","token":"[redacted]","listing":7},"level":200,"level_name":"INFO","channel":"local","datetime":"2026-11-24T16:17:29.396647+00:00","extra":{"request_id":"real-run-002"}}
$ tail -1 storage/logs/laravel.log
[2026-11-24 16:17:29] local.INFO: Code sent to 010*****678 {"phone":{"App\\Support\\PhoneNumber":"015*****678"},"token":"[redacted]","listing":7} {"request_id":"real-run-002"}
```

**Crash outside `local`.** The first try is not counted. I set `APP_ENV=staging` in `.env`, but Sail's `artisan serve` passes the `APP_ENV` it started with through to its workers, so that request still ran as `local` and showed the trace. The output above comes from a second server started inside the container with `APP_ENV=staging`, called from inside the container. `.env` is back to `local`.

## Not done and follow-ups

- **Scribe** (API docs for new endpoints, DoD 7) is not installed yet. `GET /api/v1/ping` is temporary, until step 1.4.
- **TrustProxies** is not configured. Behind Dokploy's proxy, the per-IP limit and `X-Request-Id` coming from the proxy depend on it. This belongs to step 4.4 (`DECISIONS.md` 26).
- **The JSON log is not rotated** (the `single` driver). Production will log to `stderr` (also redacted), so this matters only locally.
- **`composer test:mutate` is not in CI.** Coverage is off there (`coverage: none`). Turning on pcov in a separate CI step would add about 15 s.
- **A `PhoneNumber` inside an Eloquent model's `toArray()` serializes as `{}`**, because it doesn't implement `JsonSerializable`, so it cannot leak. API resources must choose `mask()` or `e164()` explicitly (step 1.4).

## Deploy notes

- No migrations.
- New package: `brick/money` 0.15.2 (runtime).
- New `.env` keys, all optional with defaults: `AWEZ_API_RATE_LIMIT_PER_USER` (60), `AWEZ_API_RATE_LIMIT_PER_IP` (300), `LOG_JSON_PATH`.
- Changed defaults: `APP_LOCALE=ar`, `LOG_STACK=single,json`.
- In production: destructive database commands (`migrate:fresh`, `db:wipe`, ...) refuse to run, and URLs are generated with `https`.
