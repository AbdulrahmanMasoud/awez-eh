# Task 006: Admin panel foundation

- **Branch:** `task/006-admin-panel-foundation`, in `.worktrees/006-admin-panel-foundation` (`docs/tasks/README.md`).
- **Depends on:** task 005 (merged as #8).
- **Build plan step:** 1.5. Before coding, expand it in `docs/BUILD_PLAN.md` into **Acceptance** and **Verify** lines from this brief.
- **Design:** `docs/design/ADMIN_PANEL.md` and `docs/design/admin-home.html`, approved by the owner. Read both first; this brief does not repeat them.

## Why

Every content step of phase 2 needs a place where admins review, verify and correct. This task builds the panel itself, who may enter it and do what, the record of who did what, and the screens for the reference data and the users.

## Packages (named in `CLAUDE.md`, so approved)

- **Filament**, the latest stable major that supports Laravel 13 (confirm with Boost's documentation search, and use that version's APIs throughout; do not write from memory of an older major).
- **`spatie/laravel-permission`** and **Filament Shield** (the release for that Filament major).
- **`spatie/laravel-activitylog`**.

Report the exact versions.

## 1. The panel

- One panel, `admin`, at `/admin`, as `ADMIN_PANEL.md` describes: Arabic by default and RTL, English from the user menu (stored in `users.locale`), the custom theme (`php artisan make:filament-theme`) with the brand tokens and Readex Pro, dark mode off, collapsible sidebar.
- The theme is built with Vite inside Sail (`sail npm …`). Feature tests do not need built assets (`withoutVite()` where Filament asks for the manifest); CI builds the theme once so a broken theme fails the build.
- Navigation groups and their order exactly as `ADMIN_PANEL.md` lists them. Groups with no resource yet do not show.
- The top bar's global search covers users (by name, email or phone number, a typed number read through `PhoneNumber` so `0101 234 5678` finds `+201012345678`) and the reference data. Results show masked phone numbers.
- Database notifications on (the bell), for later steps.

## 2. Who may enter

- An admin is a `users` row with at least one admin role and a password. `User::canAccessPanel()`: an admin role, `status` `active`, and a password set. Residents never reach the panel (403), even if signed in to the API.
- **Two-factor authentication is required** for every admin, with Filament's built-in multi-factor authentication (an authenticator app, with recovery codes). An admin without it set up is sent to set it up before anything else.
- Sign-in is rate limited (Filament's own limit; confirm it and say what it is), and a suspended admin is signed out on the next request.
- **`php artisan awez:create-admin`**: asks for name, email, password (hidden, confirmed, Laravel's `Password::defaults()` with a minimum of 12) and role, creates or promotes the user, and prints nothing secret. It is the only way to make the first `super_admin`, in every environment. No admin with a known password is ever seeded, except the demo seeder's `admin@awez.test` in `local` and `testing` only (and say its password in the report, not in the README).

## 3. Roles and permissions

- Roles (ARCHITECTURE §4), seeded by `ReferenceDataSeeder` everywhere (idempotent):
    - `super_admin`: everything, including roles and the activity log;
    - `moderator`: review and moderation (from 2.2), reports, users (view, suspend);
    - `data_entry`: reference data, places and the city guide content (from 2.4 and 3.5);
    - `sales`: brokered properties, agreements, leads and commissions (from 2.2 and 4.3).
- Permissions through Shield, named by its convention. For this task's resources: `super_admin` all; `moderator` view users and suspend them; `data_entry` manage cities, districts, trades and place categories; `sales` view users.
- **Policies stay one per model.** Shield's admin checks go into the existing policies next to the resident rules from tasks 003–005, and the resident rules keep passing their tests unchanged. Write down in `DECISIONS.md` how the two sit together (a resident never holds a permission, an admin's permission never widens what the API shows, because API queries are scoped, not authorized by `viewAny`).
- Cross-user access exists only here, behind these roles (ADR 0002).

## 4. Activity log

- `spatie/laravel-activitylog` on cities, districts, trades, place categories and users (status and roles only: never personal data in the log).
- Suspending, reactivating and changing roles write an entry with the admin, the reason and the before and after.
- A read-only **سجل النشاط** page for `super_admin`: who, what, which record, when (Cairo time), filterable by admin, action and date.
- From now on, every admin action in later steps writes an entry; say so in `CLAUDE.md`'s Admin panel paragraph if it does not already.

## 5. Resources

All labels in `lang/ar` and `lang/en`; Arabic is what the owner reviews. Tables sort and filter on indexed columns only.

- **المدن** (cities): list, create, edit; `is_active` instead of delete (DECISIONS 42). The center as latitude and longitude inputs for now; a map picker (MapLibre, ADR 0005) is a later step: record it as a follow-up.
- **المناطق** (districts): under their city (a relation manager on the city, and a list of their own), with `sort_order` reorderable; center optional.
- **الحرف** (trades): list, create, edit, reorder, activate. The icon as a select of Lucide names (DECISIONS 44), showing the icon.
- **أقسام الأماكن** (place categories): the same, with the parent.
- **المستخدمين** (users):
    - list: name, masked phone, email, status, roles, joined, last seen; filters: status, has a confirmed phone, provider, advertiser, admin;
    - view page: the profile (masked phone, with a "show number" action for `super_admin` and `moderator` only, which writes an activity entry), linked sign-in providers, devices, counts of their content;
    - actions: **suspend** (a required reason; signs out every device, as `account_suspended` already blocks the API) and **reactivate**, through Identity Actions (`SuspendUser`, `ReactivateUser`), never a direct update; **roles** (`super_admin` only, never removing the last `super_admin`);
    - no create, no edit of personal data, no delete: residents manage their own accounts (task 005).

## 6. The dashboard

The header and the layout of `ADMIN_PANEL.md`, with the widgets whose data exists now: **مستني مراجعة** (pending review across properties, providers, places and used items, with the oldest one's age), **مستخدمين جداد** (with the share that confirmed a phone), and **إعلانات منشورة** (split by kind), all following the period filter. The other widgets land with their steps (`ADMIN_PANEL.md`: never fake data).

## 7. Tests

- Access: a guest is sent to sign in; a resident gets 403; an admin without 2FA is sent to set it up; a suspended admin is refused; each role sees exactly its navigation.
- For every resource, per role: list, create, edit and each action allowed or forbidden as section 3 says (Filament's Livewire testing helpers).
- Suspend and reactivate: the Actions, their activity entries, the tokens revoked; the last `super_admin` cannot lose the role.
- Global search finds a user by a phone number typed with spaces.
- The dashboard's numbers against factory data, for each period.
- `awez:create-admin`: creates, promotes, refuses a short password, prints no password.
- Arch: Filament resources live in `App\Filament`; `App\Domains` still does not use `App\Filament`.

## Out of scope

Moderation screens and every content resource (properties, providers, places, used items, requests, reports: phase 2), commissions (4.3), featured listings (4.1), the city guide's screens (3.5), a map picker, dark mode.

## Acceptance

- `sail composer check` green, test counts in the report; the theme builds.
- Migrations (permission and activity log tables) verified on `testing` as the definition of done says.
- Real run on the dev stack: `awez:create-admin` for the owner's first account, then signing in at `http://localhost:8020/admin`, setting up 2FA, and opening each resource. If you can drive a browser, add screenshots of the dashboard and the users list in Arabic to the report; if not, check each page answers 200 for an admin and 403 for a resident, and list in the report what the owner should look at.
- The owner's steps in the report: how to create his own admin account on his machine.
