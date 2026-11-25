<?php

declare(strict_types=1);

use App\Domains\Bookings\Models\BookingEvent;
use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\Models\Lead;
use App\Exceptions\AppendOnlyViolation;
use App\Support\Database\IsAppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

dataset('append-only models', [
    'leads' => [Lead::class, ['void_reason' => 'changed']],
    'booking events' => [BookingEvent::class, ['note' => 'changed']],
    'commission entries' => [CommissionEntry::class, ['note' => 'changed']],
]);

/**
 * Runs a write in a savepoint, so a refused statement does not end the test's transaction.
 */
function refusedByTheDatabase(Closure $write): void
{
    expect(fn () => DB::transaction($write))->toThrow(QueryException::class, 'is append-only');
}

it('refuses to update or delete a row in PHP, before the database has to', function (string $model, array $change): void {
    /** @var Model $row */
    $row = $model::factory()->create();

    expect(fn () => $row->forceFill($change)->save())->toThrow(AppendOnlyViolation::class, 'is append-only')
        ->and(fn () => $row->delete())->toThrow(AppendOnlyViolation::class, 'is append-only');
})->with('append-only models');

it('refuses an update and a delete in the database, through Eloquent and through DB::table()', function (string $model, array $change): void {
    /** @var Model $row */
    $row = $model::factory()->create();
    $table = $row->getTable();

    refusedByTheDatabase(fn () => $model::query()->whereKey($row->getKey())->update($change));
    refusedByTheDatabase(fn () => $model::query()->whereKey($row->getKey())->delete());
    refusedByTheDatabase(fn () => DB::table($table)->where('id', $row->getKey())->update($change));
    refusedByTheDatabase(fn () => DB::table($table)->where('id', $row->getKey())->delete());
    refusedByTheDatabase(fn () => DB::statement("truncate {$table} cascade"));

    expect($model::query()->whereKey($row->getKey())->exists())->toBeTrue();
})->with('append-only models');

it('still takes new rows', function (string $model): void {
    /** @var class-string<Model> $model */
    $model::factory()->count(2)->create();

    expect($model::query()->count())->toBeGreaterThanOrEqual(2);
})->with('append-only models');

it('records only when a row was created', function (string $model): void {
    /** @var Model $row */
    $row = $model::factory()->create();

    expect($row->getAttribute('created_at'))->not->toBeNull()
        ->and($row->getUpdatedAtColumn())->toBeNull();
})->with('append-only models');

it('gives a trigger to exactly the tables whose models are append-only', function (): void {
    $guarded = array_values(array_filter(
        modelClasses(),
        fn (string $model): bool => in_array(IsAppendOnly::class, class_uses_recursive($model), true),
    ));
    $tables = array_map(fn (string $model): string => new $model()->getTable(), $guarded);
    sort($tables);

    $triggered = DB::table('pg_trigger')
        ->join('pg_class', 'pg_class.oid', '=', 'pg_trigger.tgrelid')
        ->where('pg_trigger.tgname', 'like', '%append_only%')
        ->whereNot('pg_trigger.tgisinternal')
        ->distinct()
        ->orderBy('pg_class.relname')
        ->pluck('pg_class.relname')
        ->all();

    expect($tables)->toBe(['booking_events', 'commission_entries', 'leads'])
        ->and($triggered)->toBe($tables);
});
