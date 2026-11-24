<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

it('keeps every model in a domain\'s Models namespace', function (): void {
    $elsewhere = array_filter(
        modelClasses(),
        fn (string $model): bool => preg_match('/\AApp\\\\Domains\\\\[A-Za-z]+\\\\Models\\\\[A-Za-z]+\z/', $model) !== 1,
    );

    expect(array_values($elsewhere))->toBe([]);
});

it('makes every model final, keyed by UUID v7 and explicit about what it fills', function (string $model): void {
    $class = new ReflectionClass($model);
    $fillable = $class->getAttributes(Fillable::class) !== []
        || $class->getProperty('fillable')->getDeclaringClass()->getName() === $model;

    expect($class->isFinal())->toBeTrue("{$model} is not final")
        ->and(class_uses_recursive($model))->toContain(HasUuids::class)
        ->and($fillable)->toBeTrue("{$model} declares neither #[Fillable] nor \$fillable");
})->with(fn (): array => modelClasses());
