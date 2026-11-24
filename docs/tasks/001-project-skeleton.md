# Task 001: Project skeleton and quality gate

- **Branch:** `task/001-project-skeleton`, in `.worktrees/001-project-skeleton` (`docs/tasks/README.md`).
- **Depends on:** nothing.
- **Build plan step:** 1.1 (acceptance criteria in `docs/BUILD_PLAN.md`).

## Why

The repository holds only the documents. Nothing can be built until there is a Laravel application with the same quality gate the owner uses on SellBubble, running locally in Sail and on every push in CI.

Read `CLAUDE.md`, `docs/ARCHITECTURE.md`, `docs/BUILD_PLAN.md` and `DECISIONS.md` first.

## 1. The application

- A fresh Laravel 13 application (the latest stable release), **without a starter kit**: this repository is a JSON API and a Filament panel. Pest as the test framework.
- `laravel new` cannot install into a folder that is not empty. Create the app in a temporary folder and move it in, keeping `docs/`, `CLAUDE.md`, `DECISIONS.md`, `PROGRESS.md` and this repository's `.git`. Merge Laravel's `README.md` into ours rather than overwriting it.
- API routing installed (`php artisan install:api`, which brings Sanctum). Filament is **not** installed in this task (step 1.5).
- Primary keys are UUID v7. Change the default `users` migration and model now, before anything is deployed, and record it in `DECISIONS.md`.

## 2. Sail

- Sail with the PHP 8.5 runtime, PostgreSQL 18 **with PostGIS**, and Redis.
- PostgreSQL comes from an official `postgis/postgis` image for PostgreSQL 18. Check the exact tag that exists today; if no PostgreSQL 18 image is published, stop and say so instead of choosing another major.
- Every host port comes from `.env` (`APP_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`) so the stack runs beside SellBubble on the same laptop. Pick defaults that do not clash with SellBubble's `.env.example`.
- A `testing` database is created next to the dev database (Sail's init script or our own).

## 3. The database

- The first migration enables `postgis` and `pg_trgm` (`CREATE EXTENSION IF NOT EXISTS`), with a working `down()`.
- Feature tests prove:
    - the connection is PostgreSQL 18 on the `testing` database;
    - `postgis` and `pg_trgm` are installed (query `pg_extension`), and `ST_Distance` between two `geography` points returns a number;
    - Redis answers;
    - an outbound HTTP call that is not faked is refused (`Http::preventStrayRequests()` is on for every test);
    - `GET /up` returns 200.
- Tests never use SQLite (`phpunit.xml` points at the Sail PostgreSQL).

## 4. The quality gate

Same tools and settings as SellBubble, adapted to a project without a frontend:

- **Pint** with the Laravel preset and `declare_strict_types`.
- **Rector** with `driftingly/rector-laravel` at the Laravel 13 level set, no pending changes.
- **Larastan** at `level: max`, no baseline, no ignores.
- **Pest 5** with three suites in `phpunit.xml`: Unit, Feature, Arch. Parallel runs.
- **Architecture tests** (`tests/Arch`) for every rule in `CLAUDE.md` › Architecture tests that can be checked on an empty app: Pest presets `php`, `security`, `laravel`; strict types in `App`, `Database` and `Tests`; no debug functions; `env()` only in `config/`; Actions final with `handle`; `App\Domains` does not use `App\Http` or `App\Filament`; controllers do not use the `DB` facade; jobs implement `ShouldQueue`. Rules about namespaces that do not exist yet must still be written so they apply as soon as the first class appears.
- **Composer scripts:** `setup` (install, key, migrate the dev and testing databases), `dev` (queue worker and logs), `check` (`lint:check`, `rector:check`, `analyse`, `test`), `test`, `fmt`.
- **Laravel Boost** for development: its MCP server and skills only. Do not let it append its guidelines block to `CLAUDE.md`.

Ask before adding any package not named in this brief or in `CLAUDE.md`.

## 5. CI

- `.github/workflows/ci.yml` runs on every push and pull request: PHP 8.5, a PostgreSQL 18 + PostGIS service, Redis. Each part of `composer check` is its own step, so a failure names the tool.
- Cache Composer's downloads.

## 6. Docs

- `README.md`: what the project is in two lines, how to start it with Sail (`sail up -d`, `sail composer setup`), the commands, and links to `CLAUDE.md` and `docs/`.
- Tick step 1.1 in `docs/BUILD_PLAN.md` only after its **Verify** line has passed; update `PROGRESS.md`; add the decisions you took to `DECISIONS.md` (numbered, continuing from 7).

## Acceptance

- Fresh clone → `sail up -d` → `sail composer setup` → `curl -i localhost:$APP_PORT/up` returns 200.
- `sail composer check` is green, and its test count is in the report.
- On the `testing` database: `migrate:fresh --seed`, `migrate:rollback`, then `migrate` succeed.
- A throwaway non-final Action, a throwaway `env()` call outside `config/` and a throwaway `dd()` each fail the arch tests (then removed; say so in the report).
- CI is green on the PR.

## Out of scope

Filament, Scribe, spatie packages, domains and tables beyond `users`: later steps.
