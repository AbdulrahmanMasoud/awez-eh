<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * The relationships a model declares: public methods without parameters typed to return one.
 *
 * @param  class-string<Model>  $model
 * @return array<string, Relation<Model, Model, mixed>>
 */
function relationshipsOf(string $model): array
{
    $instance = new $model;
    $relationships = [];

    foreach (new ReflectionClass($model)->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        $type = $method->getReturnType();

        if ($method->isStatic() || $method->getNumberOfParameters() > 0 || ! $type instanceof ReflectionNamedType || ! is_a($type->getName(), Relation::class, true)) {
            continue;
        }

        $relationships[$method->getName()] = $instance->{$method->getName()}();
    }

    return $relationships;
}

/**
 * Why a foreign key has no relationship on one of its sides, or nothing when both exist.
 *
 * @param  array<string, class-string<Model>>  $models
 * @return list<string>
 */
function missingRelationships(string $table, string $column, string $references, array $models): array
{
    $child = $models[$table] ?? null;
    $parent = $models[$references] ?? null;
    $missing = [];

    if ($child === null && $parent === null) {
        return ["neither {$table} nor {$references} has a model"];
    }

    if ($child === null) {
        // A pivot table: both sides reach each other through it.
        $through = array_filter(
            relationshipsOf($parent),
            fn (Relation $relation): bool => $relation instanceof BelongsToMany && $relation->getTable() === $table && $relation->getForeignPivotKeyName() === $column,
        );

        return $through === [] ? ["{$parent} has no belongsToMany through {$table}.{$column}"] : [];
    }

    $belongsTo = array_filter(
        relationshipsOf($child),
        fn (Relation $relation): bool => $relation instanceof BelongsTo && $relation->getForeignKeyName() === $column && $relation->getRelated()->getTable() === $references,
    );

    if ($belongsTo === []) {
        $missing[] = "{$child} has no belongsTo for {$table}.{$column}";
    }

    $hasMany = $parent === null ? [] : array_filter(
        relationshipsOf($parent),
        fn (Relation $relation): bool => $relation instanceof HasOneOrMany && ! $relation instanceof MorphOneOrMany && $relation->getForeignKeyName() === $column && $relation->getRelated()::class === $child,
    );

    if ($parent !== null && $hasMany === []) {
        $missing[] = "{$parent} has no hasOne or hasMany for {$table}.{$column}";
    }

    return $missing;
}

it('declares a relationship on both sides of every foreign key', function (): void {
    $models = [];

    foreach (modelClasses() as $model) {
        $models[new $model()->getTable()] = $model;
    }

    $foreignKeys = DB::select(<<<'SQL'
        select c.conrelid::regclass::text as table_name, a.attname as column_name, c.confrelid::regclass::text as references_table
        from pg_constraint c join pg_attribute a on a.attrelid = c.conrelid and a.attnum = c.conkey[1]
        where c.contype = 'f' and c.connamespace = 'public'::regnamespace
          -- Framework and package tables whose models are not ours: sessions, and spatie/laravel-permission's.
          and c.conrelid::regclass::text not in ('sessions', 'model_has_permissions', 'model_has_roles', 'role_has_permissions')
        order by 1, 2
        SQL);

    $missing = array_merge(...array_map(
        fn (object $key): array => missingRelationships($key->table_name, $key->column_name, $key->references_table, $models),
        $foreignKeys,
    ));

    expect($foreignKeys)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});
