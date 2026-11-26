<?php

declare(strict_types=1);

use App\Support\Casts\MoneyCast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rows of a catalog query, as plain arrays.
 *
 * @param  list<mixed>  $bindings
 * @return list<array<string, mixed>>
 */
function catalog(string $sql, array $bindings = []): array
{
    return array_map(fn (object $row): array => (array) $row, DB::select($sql, $bindings));
}

it('indexes every foreign key, with the key columns leading a full index', function (): void {
    $unindexed = catalog(<<<'SQL'
        select c.conrelid::regclass::text || '(' || array_to_string(array(
            select a.attname from unnest(c.conkey) with ordinality k(attnum, n)
            join pg_attribute a on a.attrelid = c.conrelid and a.attnum = k.attnum order by n
        ), ', ') || ')' as foreign_key
        from pg_constraint c
        where c.contype = 'f' and c.connamespace = 'public'::regnamespace
          and not exists (
              select 1 from pg_index i
              where i.indrelid = c.conrelid and i.indpred is null
                and array(select unnest((i.indkey::int2[])[0:cardinality(c.conkey) - 1])) = c.conkey
          )
        order by 1
        SQL);

    expect(array_column($unindexed, 'foreign_key'))->toBe([]);
});

it('gives every geography column a GiST index', function (): void {
    $columns = catalog(<<<'SQL'
        select c.table_name || '.' || c.column_name as name,
               exists (
                   select 1 from pg_index i
                   join pg_class ix on ix.oid = i.indexrelid
                   join pg_am am on am.oid = ix.relam
                   join pg_attribute a on a.attrelid = i.indrelid and a.attnum = i.indkey[0]
                   where i.indrelid = quote_ident(c.table_name)::regclass and am.amname = 'gist' and a.attname = c.column_name
               ) as indexed
        from information_schema.columns c
        where c.table_schema = 'public' and c.udt_name = 'geography'
        order by 1
        SQL);

    expect($columns)->not->toBeEmpty()
        ->and(array_column(array_filter($columns, fn (array $column): bool => ! $column['indexed']), 'name'))->toBe([]);
});

it('gives every search_text column a GIN trigram index', function (): void {
    $columns = catalog(<<<'SQL'
        select c.table_name as name,
               exists (
                   select 1 from pg_index i
                   join pg_class ix on ix.oid = i.indexrelid
                   join pg_am am on am.oid = ix.relam
                   join pg_opclass oc on oc.oid = i.indclass[0]
                   join pg_attribute a on a.attrelid = i.indrelid and a.attnum = i.indkey[0]
                   where i.indrelid = quote_ident(c.table_name)::regclass
                     and am.amname = 'gin' and oc.opcname = 'gin_trgm_ops' and a.attname = 'search_text'
               ) as indexed
        from information_schema.columns c
        where c.table_schema = 'public' and c.column_name = 'search_text'
        order by 1
        SQL);

    expect(array_column($columns, 'name'))->toBe(['guide_entries', 'news_items', 'places', 'properties', 'providers', 'requests', 'search_alerts', 'used_items'])
        ->and(array_column(array_filter($columns, fn (array $column): bool => ! $column['indexed']), 'name'))->toBe([]);
});

it('keys every model table by a UUID primary key', function (string $model): void {
    $table = new $model()->getTable();

    $primaryKey = catalog(<<<'SQL'
        select a.attname as column_name, format_type(a.atttypid, a.atttypmod) as type
        from pg_index i join pg_attribute a on a.attrelid = i.indrelid and a.attnum = any(i.indkey)
        where i.indrelid = ?::regclass and i.indisprimary
        SQL, [$table]);

    expect($primaryKey)->toBe([['column_name' => 'id', 'type' => 'uuid']]);
})->with(fn (): array => modelClasses());

it('stores every enum as a string the database checks against the enum', function (string $model): void {
    $instance = new $model;
    $table = $instance->getTable();
    $enums = array_filter($instance->getCasts(), enum_exists(...));

    foreach ($enums as $column => $enum) {
        $definition = DB::scalar(
            'select pg_get_constraintdef(oid) from pg_constraint where conrelid = ?::regclass and conname = ?',
            [$table, "{$table}_{$column}_check"],
        );

        expect($definition)->toBeString("{$table}.{$column} has no CHECK constraint");

        preg_match_all("/'([^']+)'::character varying/", (string) $definition, $matches);
        $allowed = $matches[1];
        $values = array_map(fn (BackedEnum $case): string|int => $case->value, $enum::cases());
        sort($allowed);
        sort($values);

        expect($allowed)->toBe($values, "{$table}.{$column} allows other values than {$enum}");
    }
})->with(fn (): array => array_values(array_filter(
    modelClasses(),
    fn (string $model): bool => array_filter(new $model()->getCasts(), enum_exists(...)) !== [],
)));

it('stores money as bigint minor units beside a three-letter currency', function (string $model): void {
    $instance = new $model;
    $table = $instance->getTable();

    foreach ($instance->getCasts() as $column => $cast) {
        if (! Str::startsWith($cast, MoneyCast::class)) {
            continue;
        }

        $currency = Str::contains($cast, ':') ? explode(',', Str::after($cast, ':'))[0] : 'currency';
        $types = catalog(
            'select column_name, format_type(atttypid, atttypmod) as type from information_schema.columns c
             join pg_attribute a on a.attrelid = ?::regclass and a.attname = c.column_name
             where c.table_schema = ? and c.table_name = ? and c.column_name in (?, ?) order by column_name = ? desc',
            [$table, 'public', $table, $column, $currency, $column],
        );

        expect($types)->toBe([
            ['column_name' => $column, 'type' => 'bigint'],
            ['column_name' => $currency, 'type' => 'character(3)'],
        ]);
    }
})->with(fn (): array => array_values(array_filter(
    modelClasses(),
    fn (string $model): bool => array_filter(new $model()->getCasts(), fn (string $cast): bool => Str::startsWith($cast, MoneyCast::class)) !== [],
)));

it('gives every foreign key the delete rule its data needs', function (): void {
    $rules = catalog(<<<'SQL'
        select c.conrelid::regclass::text || '.' || a.attname as foreign_key,
               case c.confdeltype when 'c' then 'cascade' when 'n' then 'set null' when 'r' then 'restrict' else 'no action' end as on_delete
        from pg_constraint c join pg_attribute a on a.attrelid = c.conrelid and a.attnum = c.conkey[1]
        where c.contype = 'f' and c.connamespace = 'public'::regnamespace
        order by 1
        SQL);

    expect(array_column($rules, 'on_delete', 'foreign_key'))->toBe([
        'booking_events.actor_id' => 'restrict',
        'booking_events.booking_id' => 'restrict',
        'bookings.counterpart_id' => 'restrict',
        // Histories: a booking, its events, a lead and a commission entry keep their parents for good.
        'bookings.resident_id' => 'restrict',
        'commission_entries.lead_id' => 'restrict',
        'commission_entries.property_agreement_id' => 'restrict',
        'commission_entries.property_id' => 'restrict',
        'commission_entries.recorded_by' => 'restrict',
        'conversations.counterpart_id' => 'cascade',
        'conversations.resident_id' => 'cascade',
        'device_tokens.personal_access_token_id' => 'cascade',
        'device_tokens.user_id' => 'cascade',
        // Reference data is never deleted from under content: admins deactivate it instead.
        'districts.city_id' => 'cascade',
        'duty_pharmacies.place_id' => 'cascade',
        'favorites.user_id' => 'cascade',
        'featured_placements.featured_package_id' => 'restrict',
        'featured_placements.granted_by' => 'set null',
        'featured_placements.user_id' => 'cascade',
        'guide_entries.city_id' => 'restrict',
        'leads.property_id' => 'restrict',
        'leads.resident_id' => 'restrict',
        'leads.voids_lead_id' => 'restrict',
        'messages.conversation_id' => 'cascade',
        // Checked at the end of the statement: a conversation deleted with its resident takes its messages,
        // but a sender's messages in someone else's conversation keep the sender's account.
        'messages.sender_id' => 'no action',
        'model_has_permissions.permission_id' => 'cascade',
        'model_has_roles.role_id' => 'cascade',
        'news_items.city_id' => 'restrict',
        'news_items.created_by' => 'set null',
        'opening_hours.place_id' => 'cascade',
        'pending_uploads.user_id' => 'cascade',
        'phone_verifications.user_id' => 'cascade',
        'place_categories.parent_id' => 'restrict',
        'place_items.place_id' => 'cascade',
        'place_offers.place_id' => 'cascade',
        'places.city_id' => 'restrict',
        'places.district_id' => 'restrict',
        'places.place_category_id' => 'restrict',
        // A place an admin added outlives the account that claimed it.
        'places.reviewed_by' => 'set null',
        'places.user_id' => 'set null',
        'properties.city_id' => 'restrict',
        'properties.district_id' => 'restrict',
        'properties.reviewed_by' => 'set null',
        // Accounts are never hard-deleted (DECISIONS 56): DeleteAccount archives or deletes the content first.
        'properties.user_id' => 'cascade',
        'property_agreements.created_by' => 'set null',
        'property_agreements.property_id' => 'cascade',
        'provider_prices.provider_id' => 'cascade',
        'provider_trades.provider_id' => 'cascade',
        'provider_trades.trade_id' => 'restrict',
        'providers.city_id' => 'restrict',
        'providers.district_id' => 'restrict',
        'providers.reviewed_by' => 'set null',
        'providers.user_id' => 'cascade',
        'reports.resolved_by' => 'set null',
        'reports.user_id' => 'cascade',
        'request_replies.request_id' => 'cascade',
        'request_replies.user_id' => 'cascade',
        'requests.city_id' => 'restrict',
        'requests.district_id' => 'restrict',
        'requests.place_category_id' => 'restrict',
        'requests.trade_id' => 'restrict',
        'requests.user_id' => 'cascade',
        'reviews.booking_id' => 'restrict',
        'reviews.hidden_by' => 'set null',
        'reviews.reviewer_id' => 'cascade',
        'role_has_permissions.permission_id' => 'cascade',
        'role_has_permissions.role_id' => 'cascade',
        'search_alerts.district_id' => 'restrict',
        'search_alerts.user_id' => 'cascade',
        'sessions.user_id' => 'cascade',
        'social_accounts.user_id' => 'cascade',
        'used_items.city_id' => 'restrict',
        'used_items.district_id' => 'restrict',
        'used_items.reviewed_by' => 'set null',
        'used_items.user_id' => 'cascade',
        'users.district_id' => 'restrict',
    ]);
});

it('gives moderated tables the same moderation columns, starting at pending_review', function (string $table): void {
    $columns = catalog(
        'select column_name, data_type, is_nullable, column_default from information_schema.columns
         where table_schema = ? and table_name = ? and column_name in (?, ?, ?, ?, ?) order by column_name',
        ['public', $table, 'published_at', 'rejection_reason', 'reviewed_at', 'reviewed_by', 'status'],
    );

    expect($columns)->toBe([
        ['column_name' => 'published_at', 'data_type' => 'timestamp without time zone', 'is_nullable' => 'YES', 'column_default' => null],
        ['column_name' => 'rejection_reason', 'data_type' => 'text', 'is_nullable' => 'YES', 'column_default' => null],
        ['column_name' => 'reviewed_at', 'data_type' => 'timestamp without time zone', 'is_nullable' => 'YES', 'column_default' => null],
        ['column_name' => 'reviewed_by', 'data_type' => 'uuid', 'is_nullable' => 'YES', 'column_default' => null],
        ['column_name' => 'status', 'data_type' => 'character varying', 'is_nullable' => 'NO', 'column_default' => "'pending_review'::character varying"],
    ])->and(indexColumns($table))->toContain('status, published_at');
})->with(['places', 'properties', 'providers', 'used_items']);

/**
 * The column lists of a table's indexes, each written as "a, b", partial ones with their condition.
 *
 * @return list<string>
 */
function indexColumns(string $table): array
{
    return array_column(catalog(<<<'SQL'
        select array_to_string(array(
                   select pg_get_indexdef(i.indexrelid, k.n::int, true) from generate_series(1, i.indnkeyatts) k(n)
               ), ', ') || case when i.indisunique then ' unique' else '' end
               || coalesce(' where ' || pg_get_expr(i.indpred, i.indrelid, true), '') as columns
        from pg_index i where i.indrelid = ?::regclass
        order by 1
        SQL, [$table]), 'columns');
}

it('indexes what lists filter and sort by', function (string $table, array $expected): void {
    expect(indexColumns($table))->toContain(...$expected);
})->with([
    'cities' => ['cities', ['slug unique']],
    'districts' => ['districts', ['city_id, slug unique', 'city_id, sort_order']],
    'properties' => ['properties', ['purpose, type', 'district_id', 'price', 'city_id', 'user_id', 'location', 'search_text']],
    'property_agreements' => ['property_agreements', ['property_id', 'created_by']],
    'trades' => ['trades', ['slug unique', 'is_active, sort_order']],
    'providers' => ['providers', ['user_id unique', 'city_id', 'district_id', 'verification', 'is_available', 'location', 'search_text']],
    'provider_trades' => ['provider_trades', ['provider_id, trade_id unique', 'trade_id', 'provider_id unique where is_primary']],
    'provider_prices' => ['provider_prices', ['provider_id, sort_order']],
    'place_categories' => ['place_categories', ['slug unique', 'parent_id, sort_order']],
    'places' => ['places', ['user_id', 'place_category_id', 'city_id', 'district_id', 'delivers', 'location', 'search_text']],
    'place_items' => ['place_items', ['place_id, sort_order']],
    'place_offers' => ['place_offers', ['place_id, ends_at']],
    'opening_hours' => ['opening_hours', ['place_id, weekday']],
    'used_items' => ['used_items', ['user_id', 'category', 'condition', 'city_id', 'district_id', 'price', 'location', 'search_text']],
    'requests' => ['requests', ['user_id', 'area', 'trade_id', 'place_category_id', 'city_id', 'district_id', 'status, expires_at', 'location', 'search_text']],
    'request_replies' => ['request_replies', ['request_id', 'user_id']],
    'reports' => ['reports', ['user_id', 'reportable_type, reportable_id', 'status, created_at', 'resolved_by', "user_id, reportable_type, reportable_id unique where status::text = 'open'::text"]],
    'conversations' => ['conversations', ['subject_type, subject_id', 'resident_id, last_message_at', 'counterpart_id, last_message_at', "resident_id, subject_type, subject_id unique where status::text = 'open'::text"]],
    'messages' => ['messages', ['conversation_id, id', 'sender_id', 'location']],
    'bookings' => ['bookings', ['bookable_type, bookable_id', 'resident_id', 'counterpart_id', 'status, scheduled_at', 'location']],
    'booking_events' => ['booking_events', ['booking_id, created_at', 'actor_id']],
    'reviews' => ['reviews', ['booking_id unique', 'reviewer_id', 'reviewable_type, reviewable_id', 'hidden_by']],
    'favorites' => ['favorites', ['user_id, favoritable_type, favoritable_id unique', 'favoritable_type, favoritable_id']],
    'search_alerts' => ['search_alerts', ['user_id', 'district_id', 'is_active, frequency', 'location', 'search_text']],
    'notifications' => ['notifications', ['notifiable_type, notifiable_id']],
    'leads' => ['leads', ['property_id, created_at', 'resident_id', 'voids_lead_id unique', 'source_type, source_id unique where voids_lead_id IS NULL']],
    'commission_entries' => ['commission_entries', ['property_id, occurred_on', 'property_agreement_id', 'lead_id', 'recorded_by']],
    'featured_packages' => ['featured_packages', ['applies_to, is_active, sort_order']],
    'featured_placements' => ['featured_placements', ['featurable_type, featurable_id, ends_at', 'ends_at', 'featured_package_id', 'user_id', 'granted_by']],
    'guide_entries' => ['guide_entries', ['city_id, category, sort_order', 'location', 'search_text']],
    'duty_pharmacies' => ['duty_pharmacies', ['place_id, duty_date unique', 'duty_date']],
    'news_items' => ['news_items', ['city_id, is_published, published_at', 'created_by', 'search_text']],
]);
