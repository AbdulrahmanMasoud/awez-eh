# 0002. Ownership by policies, not row level security

## Context

The owner's other project (SellBubble) isolates tenants with PostgreSQL row level security. Awez Eh has no tenants: one public catalog that many users write to, each owning their rows.

## Decision

No RLS and no tenant scoping. Every user-owned table has an owner column; Policies decide every read of private data and every write of someone else's row. Feature tests prove that one user cannot read private data of, change or delete another user's rows. Cross-user access exists only in Filament, behind admin roles.

## Consequences

- Simpler migrations, queries and tests; one database role.
- The database does not enforce ownership, so a forgotten `authorize()` is the main risk. Architecture tests require Form Requests on write endpoints, and the ownership tests are part of the definition of done.
