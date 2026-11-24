<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
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
    // Fewer, longer-lived workers than the default: a cold run of every file (as in CI)
    // otherwise runs the Sail container out of memory and leaves files unchecked.
    ->withParallel(timeoutSeconds: 600, maxNumberOfProcess: 4);
