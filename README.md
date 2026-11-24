# Awez Eh

The backend of Awez Eh ("What do you want?"), a city app for Sadat City, Egypt: real estate, craftsmen, restaurants, shops and pharmacies, second-hand items, a city guide and requests. A versioned JSON API for the mobile app and a Filament admin panel, on Laravel 13, PostgreSQL 18 with PostGIS, and Redis.

## Getting started

Everything runs in [Laravel Sail](https://laravel.com/docs/sail) (Docker). Host PHP is not used.

```bash
git clone git@github.com:AbdulrahmanMasoud/awez-eh.git && cd awez-eh
cp .env.example .env
# Once, on a fresh clone: Sail itself lives in vendor/, so the first install runs in Composer's image.
docker run --rm -v "$(pwd):/app" -w /app composer:2 composer install
vendor/bin/sail up -d
vendor/bin/sail composer setup
curl -i http://localhost:8020/up
```

`composer setup` installs the dependencies, generates the app key, migrates and seeds the dev database (`awez_eh`), and migrates the `testing` database next to it.

The host ports come from `.env` (`APP_PORT=8020`, `FORWARD_DB_PORT=5452`, `FORWARD_REDIS_PORT=6399`), so the stack runs beside other projects. On Apple silicon the PostGIS image runs emulated: it is published for amd64 only.

A `sail` alias saves typing:

```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

## Commands

```bash
sail up -d                  # app (http://localhost:8020), PostgreSQL + PostGIS, Redis
sail composer dev           # queue worker and logs
sail composer check         # the gate: Pint, Rector, Larastan, Pest (unit + feature + arch)
sail composer test          # Pest unit + feature + arch, in parallel
sail composer fmt           # Rector + Pint (writes)
sail artisan migrate:fresh --seed
```

Tests run on the `testing` database of the same PostgreSQL, never on SQLite and never on the dev database. CI (`.github/workflows/ci.yml`) runs each part of `composer check` as its own step on every push and pull request.

## Documentation

- [`CLAUDE.md`](CLAUDE.md): the rules for everyone working on the code.
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md): the design.
- [`docs/BUILD_PLAN.md`](docs/BUILD_PLAN.md): the order of the work; [`PROGRESS.md`](PROGRESS.md) and [`DECISIONS.md`](DECISIONS.md) track it.
- [`docs/adr/`](docs/adr): architecture decisions.
- [`docs/tasks/`](docs/tasks): the briefs; [`docs/reports/`](docs/reports): what was done.
