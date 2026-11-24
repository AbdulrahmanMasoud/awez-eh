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
