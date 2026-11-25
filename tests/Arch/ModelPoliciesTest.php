<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Reads the schema, so this file boots the application on the testing database.
pest()->extend(TestCase::class)->use(RefreshDatabase::class);

it('gives every model with an owner column a policy (invariant 1)', function (): void {
    $owned = array_filter(
        modelClasses(),
        fn (string $model): bool => Schema::hasColumn(new $model()->getTable(), 'user_id')
            || Schema::hasColumn(new $model()->getTable(), 'resident_id'),
    );
    $withoutPolicy = array_filter($owned, fn (string $model): bool => Gate::getPolicyFor($model) === null);

    expect($owned)->not->toBeEmpty()
        ->and(array_values($withoutPolicy))->toBe([]);
});
