# 0006. Admin permissions count only inside the admin panel

## Context

Admins are users with roles (`spatie/laravel-permission`, named Filament Shield's way). ADR 0002 keeps one policy per model, so the admin rules sit in the same policies as the resident rules. An admin may also be a resident: anyone can sign in to the app with Google, admins included. A policy that said "the owner, or anyone holding `Update:Property`" would let an admin change anybody's listing through the API, and Shield's usual super-admin shortcut (`Gate::before`) would grant every ability there.

## Decision

- An admin permission counts only when the request is inside the admin panel. Policies ask `$user->mayAdminister('Update:City')`, which is false outside it (`App\Support\AdminArea`, pointed at Filament's current panel by the panel's provider).
- `super_admin` holds every permission explicitly (`SyncAdminRoles`), with no gate shortcut.
- Policies stay hand-written, one per model; Shield generates no policies, only the names on its roles screen.

## Consequences

- Through the API an admin is a resident; residents hold no permission at all.
- Code outside the panel (jobs, commands) never gains an admin ability by accident. An admin action there goes through an Action that takes the admin explicitly.
- A permission added later must be added to `AdminPermissions` (a test compares it with Shield's catalogue) and is granted by seeding again.
- Tests of panel code set the current panel, as the panel's middleware does.
