# Task 005: Sign-in

- **Branch:** `task/005-sign-in`, in `.worktrees/005-sign-in` (`docs/tasks/README.md`).
- **Depends on:** task 004 (merged as #6).
- **Build plan step:** 1.4. Before coding, expand it in `docs/BUILD_PLAN.md` into **Acceptance** and **Verify** lines from this brief, as 1.3a and 1.3b have them.

## Why

Residents sign in with Google or Apple, confirm a mobile number, and get a token per device (ADR 0003). These are the first real endpoints: every later API step builds on the signed-in user, the error shape and the API docs set up here.

## Housekeeping first

As `docs/tasks/README.md` says. Tasks 001–004 are `done`; there are no worktrees left.

## 0. Packages (the owner approves both)

- **`firebase/php-jwt`**: verifies Google's and Apple's ID tokens against their published keys (JWKS). No Socialite: the mobile app gets the ID token natively, and the backend only verifies it.
- **`knuckleswtf/scribe`** (development): the generated API docs `CLAUDE.md` names (DoD 7), from routes, Form Requests and Resources. Docs at `/docs` in `local` only; the published copy comes with step 4.4.

## 1. The `users` table

Edit task 001's `users` migration rather than adding one (DECISIONS: allowed until the first deploy). The table becomes:

- `name`; `email` null, unique where not null (Apple may give a private relay address, or none on later sign-ins); `email_verified_at` null;
- `password` null (only admins have one: Filament, step 1.5); `remember_token`;
- `phone` null (`PhoneNumberCast`, mobile only), **unique where not null**, and `phone_verified_at` null. `phone` holds only a confirmed number; a number being confirmed lives in `phone_verifications`;
- `avatar_url` null (the provider's picture URL; uploads come with media, 2.1);
- `locale` (`ar`, `en`, default `ar`); `district_id` null FK `restrictOnDelete`;
- `status` (`active`, `suspended`, `deleted`) with a `CHECK`; `suspended_at` null; `deleted_at` null (the moment the account was deleted and anonymized; not Laravel's soft deletes, the row stays visible to history);
- `last_seen_at` null.

`password_reset_tokens` and `sessions` stay for Filament.

There is no role column. A resident is every user; `isAdvertiser()` and `isProvider()` are derived (owns a listing or a place; has a provider profile). Admin roles come with `spatie/laravel-permission` in step 1.5.

## 2. New tables (Identity)

- `social_accounts`: `user_id` `cascadeOnDelete`, `provider` (`google`, `apple`), `subject` (the token's `sub`), `email` null (as given at linking), `linked_at`. Unique on `(provider, subject)`; one account per provider per user.
- `phone_verifications` (named for what it holds; ARCHITECTURE's `otp_codes`, rename it there): `user_id` `cascadeOnDelete`, `phone` (E.164), `driver` (`log`, `whatsapp_inbound`), `code_hash` (never the code), `attempts` smallint, `expires_at`, `confirmed_at` null, `ip_address`. Indexes on `(phone, created_at)`, `(user_id, created_at)`, `(ip_address, created_at)` for the rate limits.
- `device_tokens` (push, ARCHITECTURE §8): `user_id` `cascadeOnDelete`, `personal_access_token_id` FK `cascadeOnDelete` (signing out of a device removes its push token), `token` unique, `platform` (`ios`, `android`), `app_version` null, `last_used_at`. Sending comes in step 3.4.

## 3. Verifying ID tokens

- `App\Domains\Identity\Contracts\IdentityTokenVerifier` with `verify(string $idToken, ?string $nonce): VerifiedIdentity` (a `final readonly` Data object: provider, subject, email, email verified, name). Two drivers:
    - **Google:** signature against Google's JWKS, `iss` (`accounts.google.com` or `https://accounts.google.com`), `aud` in `config('awez.sign_in.google.client_ids')` (the Android, iOS and web client ids, from `.env`), `exp` and `iat` with a small leeway.
    - **Apple:** signature against Apple's JWKS, `iss` `https://appleid.apple.com`, `aud` in `config('awez.sign_in.apple.client_ids')` (the bundle id), `exp`, and the `nonce` claim equal to `sha256` of the nonce the app sends (required).
- JWKS are fetched with Laravel's HTTP client and cached for the `max-age` their response gives (an hour if none). An unknown `kid` refreshes the cache once, then fails.
- Every failure is one domain error, `invalid_identity_token` (401), with the reason in its private context only.
- Tests sign tokens with a key pair generated in the test and fake the JWKS response (`Http::preventStrayRequests()` stays on): a valid token, then each way it can be wrong (signature, `kid`, `iss`, `aud`, expired, not yet valid, Apple's nonce missing or wrong).
- **A local fake driver** for real runs before the owner has the Google and Apple clients (BUILD_PLAN phase 0): `AWEZ_SIGN_IN_FAKE=true` accepts `fake:<email>` as an ID token. It is refused at boot outside `local` and `testing` (a test proves the refusal), and it is never the default.

## 4. Endpoints

All under `/api/v1`, Form Requests for every write, API Resources out, the error shape from task 002, Arabic attribute names in `lang/ar/validation.php` (`id_token`, `device_name`, `phone`, `code`, `name`, …; this also closes task 002's "حقل name مطلوب" follow-up for these fields).

- `POST auth/google` and `POST auth/apple`: `{ id_token, nonce (Apple), name (Apple sends it to the app on the first sign-in only), device_name, platform }`.
    - Find the user by `(provider, subject)`; else, if the identity's email is **verified** and belongs to an active user, link this provider to that user; else create a user. A `deleted` or `suspended` user cannot sign in (`account_deleted` is not a code: a deleted account's social links are gone, so it simply becomes a new user; `account_suspended` 403).
    - Issue a Sanctum token named after the device. Signing in again on the same device name replaces that device's token (one token per device).
    - Answer `{ data: { token, user: UserResource, needs_phone: bool } }`, 201 for a new user and 200 otherwise.
- `GET me`, `PATCH me` (`name`, `locale`, `district_id`). `UserResource` for one's own account shows the full phone number (it is theirs); nothing else ever returns another user's number (invariant 2 and 8).
- `POST me/phone` `{ phone }`: starts a confirmation (below). `POST me/phone/confirm` `{ code }`: confirms it.
- `PUT me/device-token` `{ token, platform, app_version }`: upserts the push token for the current access token.
- `POST auth/sign-out`: deletes the current token (its device token goes with it).
- `DELETE me`: deletes the account (section 6), then the token is gone.
- Remove `GET ping` and its tests (task 002 said it lasts until this step); move what they proved (locale, `Content-Language`, the resource shape) to `GET me` tests.
- A middleware on authenticated routes refuses a suspended user (`account_suspended`) and updates `last_seen_at` at most once every five minutes.

## 5. Phone confirmation (`PhoneVerifier`)

- `App\Domains\Identity\Contracts\PhoneVerifier`, chosen by `config('awez.phone_verification.driver')`:
    - `start(User, PhoneNumber): PhoneChallenge`, where `PhoneChallenge` says what the app must do: `{ method: "code", length: 6, expires_at }` for drivers where the resident types a code, or `{ method: "whatsapp", link, expires_at }` for the WhatsApp driver of step 4.3b, where the app opens a pre-filled message and polls `GET me`.
    - Build the **`log` driver** only, and shape the interface so step 4.3b adds `whatsapp_inbound` without changing the endpoints.
- The code is 6 digits from `random_int`, stored as a hash, valid 10 minutes, 5 attempts; a new start replaces an open one.
- **The `log` driver writes the code to its own `phone_verification` log channel**, the one exception to "never log a code" (invariant 8). It is refused at boot outside `local` and `testing`, its channel is not redacted, and no other channel ever receives the code (a test). Record the exception in `DECISIONS.md`.
- Rate limits (in `config/awez.php`): per phone number (3 starts in 10 minutes, 10 a day), per user (5 an hour), per IP (20 an hour). Over a limit: `too_many_attempts` 429 with `Retry-After`.
- Confirming: wrong code → `verification_code_invalid` (422, attempts left in `errors`); expired or used up → `verification_code_expired`. A number another account has confirmed → `phone_taken` (409), checked at start and again at confirmation inside the transaction (the unique index is the last guard).
- Confirmation sets `users.phone` and `phone_verified_at` in one transaction with `confirmed_at`.

## 6. Deleting an account

An account is **never hard-deleted**; it is anonymized (DECISIONS 41 and 56, the owner's decisions; this simplifies entry 41's two options to one: the row always stays). `App\Domains\Identity\Actions\DeleteAccount`, in one transaction:

- revoke every token (device tokens go with them), delete social accounts, phone verifications;
- each domain cleans up its own rows through its own Action (a domain writes only its own tables):
    - **RealEstate:** brokered properties are archived and kept, with their agreements and leads; other properties are deleted if nothing points at them, else archived;
    - **Services:** the provider profile is archived (bookings and reviews point at it);
    - **Places:** owned places lose their owner (`user_id` null) and stay as they are, since a place is a business, not a person;
    - **Marketplace, Requests, Favorites, Alerts:** used items, open requests, favorites and search alerts are deleted; requests with replies are closed instead;
    - **Moderation:** open reports the user filed are deleted;
- blank the personal data: `name` → the translated "حساب محذوف", `email`, `phone`, `avatar_url` null, `status` `deleted`, `deleted_at` now.
- Conversations, messages, bookings, reviews and leads stay, now showing the deleted account's anonymous name.
- Feature tests for each item, one test that a deleted account's data appears nowhere in any API answer, and one that signing in again with the same Google account creates a fresh user.

## 7. Ownership and arch

- Ownership proven at the API level for these endpoints: there is no endpoint taking another user's id; prove that `me` always answers for the token's user, and that user A's token cannot change B's phone, device token or account.
- Arch: classes in `App\Domains\Identity\Contracts` are interfaces; drivers implement them; nothing outside `App\Domains\Identity` uses `Firebase\JWT`.

## 8. Docs

- Scribe documents every endpoint, with examples in Arabic, the error codes each can return, and the `Accept-Language` header.
- `docs/ARCHITECTURE.md` §3 and §4 updated (tables, the anonymizing deletion). ADR 0003 gets a line on the fake driver and the log channel.

## Out of scope

Filament and admin sign-in (1.5), the WhatsApp driver (4.3b), sending pushes (3.4), roles and permissions (1.5), an email or SMS sign-in.

## Acceptance

- `sail composer check` green with the test counts in the report.
- Migrations verified on `testing` as the definition of done says, including the edited `users` migration.
- Real run with the fake driver on the dev stack (`localhost:8020`), outputs pasted in the report: sign in with a new and then the same "Google" identity, `GET me` with `needs_phone`, start a phone confirmation and read the code from the `phone_verification` log, a wrong code, the right one, `phone_taken` from a second account, the rate limit reached, a device token saved, sign-out (the token then answers 401), and an account deleted and anonymized (its row shown from psql).
- `/docs` generated, and a screenshot or the HTML of one endpoint's page in the report.
