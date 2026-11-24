<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;

it('makes a valid row with every model\'s factory', function (string $model): void {
    /** @var class-string<Model> $model */
    $created = $model::factory()->create();

    expect($created->exists)->toBeTrue()
        ->and($model::query()->whereKey($created->getKey())->exists())->toBeTrue();
})->with(fn (): array => modelClasses());
