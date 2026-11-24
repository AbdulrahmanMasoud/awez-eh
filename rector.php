<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use RectorLaravel\Set\LaravelLevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withSets([LaravelLevelSetList::UP_TO_LARAVEL_130])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withSkip([
        // A policy method's parameters are Laravel's Gate contract: the Gate reads the first one to
        // decide whether a guest may be asked at all, even when the answer ignores them.
        RemoveUnusedPublicMethodParameterRector::class => [__DIR__.'/app/Domains/*/Policies/*'],
    ])
    // Fewer, longer-lived workers than the default: a cold run of every file (as in CI)
    // otherwise runs the Sail container out of memory and leaves files unchecked.
    ->withParallel(timeoutSeconds: 600, maxNumberOfProcess: 4);
