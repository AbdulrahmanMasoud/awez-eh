<?php

declare(strict_types=1);

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

/**
 * Every class under App\Http\Controllers\Api, found from its file as PSR-4 maps it.
 *
 * @return list<class-string>
 */
function apiControllerClasses(): array
{
    $root = dirname(__DIR__, 2).'/app/Http/Controllers/Api';

    if (! is_dir($root)) {
        return [];
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $classes = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($root) + 1, -strlen('.php'));
        $classes[] = 'App\\Http\\Controllers\\Api\\'.str_replace('/', '\\', $relative);
    }

    return $classes;
}

/**
 * Why a return type is not allowed for an API action, or null when it is.
 */
function apiReturnTypeProblem(?ReflectionType $type): ?string
{
    if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
        return 'declares '.($type instanceof ReflectionType ? "the return type {$type}" : 'no return type');
    }

    $name = $type->getName();

    // A 204 comes from response()->noContent(); every body goes through a resource.
    if (is_a($name, JsonResource::class, true) || $name === Response::class) {
        return null;
    }

    return "returns {$name}";
}

it('makes every API controller action return an API resource or a 204 response', function (): void {
    $problems = [];

    foreach (apiControllerClasses() as $class) {
        $methods = new ReflectionClass($class)->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor() || $method->isStatic()) {
                continue;
            }

            $problem = apiReturnTypeProblem($method->getReturnType());

            if ($problem !== null) {
                $problems[] = "{$class}::{$method->getName()}() {$problem}";
            }
        }
    }

    expect($problems)->toBe([]);
});
