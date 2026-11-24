# 0001. Laravel with PostgreSQL, PostGIS and pg_trgm

## Context

The backend serves a mobile app and an admin panel. Half of the work is the admin side: moderating listings, verifying craftsmen, recording leads and commissions. The other options weighed were Supabase, Node.js (NestJS) and Go.

## Decision

Laravel (latest stable) with PostgreSQL 18, PostGIS for locations and pg_trgm for typo-tolerant search, Redis for cache and queues, and Filament for the admin panel.

## Consequences

- Filament gives the admin panel in days; queues, the scheduler, notifications and Reverb come with the framework.
- One server carries a city; growth is handled by adding Redis, database and app servers, Horizon, Meilisearch and Octane when measured, without changing the framework.
- The mobile app is TypeScript and the backend PHP, so types are not shared; the API is documented by Scribe instead.
