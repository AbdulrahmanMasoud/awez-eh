# 0004. Laravel Sail for local development

## Context

The owner already runs SellBubble in Sail. Local development must match production's PostgreSQL with PostGIS and Redis.

## Decision

Every command runs in Sail containers: the app on Sail's PHP runtime, PostgreSQL 18 with PostGIS, Redis. Host ports come from `.env` so the stack runs beside other projects.

## Consequences

- One `sail up -d` gives the same database extensions as production and CI.
- Host PHP is not used.
