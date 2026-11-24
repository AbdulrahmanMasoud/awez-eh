#!/usr/bin/env bash
# Runs once, when the PostgreSQL volume is first created.
#
# compose.yaml mounts this file over the postgis image's own 10_postgis.sh, which would load
# PostGIS, topology and the tiger geocoder into the dev database. Our first migration owns the
# extensions instead, so the dev and testing databases start empty and identical, and its down()
# works on both.
set -euo pipefail

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<'SQL'
SELECT 'CREATE DATABASE testing' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'testing')\gexec
SQL
