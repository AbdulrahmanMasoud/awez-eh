<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Makes a table append-only in PostgreSQL: triggers refuse every UPDATE, DELETE and TRUNCATE,
 * whoever sends it (Eloquent, the query builder, psql). Corrections are new rows.
 *
 * Called from migrations: enable() in up(), disable() in down(). The SQL is fixed and the table is
 * a bound value, so a migration that calls it means the same thing whenever it runs. PostgreSQL
 * quotes the trigger and table names itself (format's %I and regclass).
 */
final class AppendOnly
{
    /**
     * One function refuses the change; two helpers attach and detach the triggers by table. They are
     * created or replaced on each call: `migrate:fresh` drops tables, and their triggers with them,
     * but leaves functions behind.
     */
    private const string FUNCTIONS = <<<'SQL'
        create or replace function append_only_refuse() returns trigger language plpgsql as $$
        begin
            raise exception '% is append-only: % is refused. Record a correcting row instead.', tg_table_name, tg_op
                using errcode = 'restrict_violation';
        end;
        $$;

        create or replace function append_only_attach(target regclass) returns void language plpgsql as $$
        declare
            name text := (select relname from pg_class where oid = target) || '_append_only';
        begin
            execute format('create trigger %I before update or delete on %s for each row execute function append_only_refuse()', name, target);
            -- TRUNCATE skips row triggers, so it gets its own.
            execute format('create trigger %I before truncate on %s for each statement execute function append_only_refuse()', name || '_truncate', target);
        end;
        $$;

        create or replace function append_only_detach(target regclass) returns void language plpgsql as $$
        declare
            name text := (select relname from pg_class where oid = target) || '_append_only';
        begin
            execute format('drop trigger if exists %I on %s', name || '_truncate', target);
            execute format('drop trigger if exists %I on %s', name, target);
        end;
        $$;
        SQL;

    public static function enable(string $table): void
    {
        DB::unprepared(self::FUNCTIONS);
        DB::statement('select append_only_attach(?::regclass)', [$table]);
    }

    public static function disable(string $table): void
    {
        DB::unprepared(self::FUNCTIONS);
        DB::statement('select append_only_detach(?::regclass)', [$table]);
    }
}
