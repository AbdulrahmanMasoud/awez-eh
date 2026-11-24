<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Namespaces of one layer (Actions, Jobs, Models, ...) across every domain that has it.
 * Arch wildcards fail while no domain has the layer yet, so the folders are listed instead:
 * a rule starts to apply the moment the first class of its layer appears.
 *
 * @return list<string>
 */
function domainLayerNamespaces(string $layer): array
{
    $directories = glob(__DIR__.'/../app/Domains/*/'.$layer, GLOB_ONLYDIR) ?: [];

    return array_map(
        fn (string $directory): string => 'App\\Domains\\'.basename(dirname($directory)).'\\'.$layer,
        $directories,
    );
}
