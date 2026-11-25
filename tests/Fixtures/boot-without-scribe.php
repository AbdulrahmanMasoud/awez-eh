<?php

declare(strict_types=1);

/*
 * Boots the application as `composer install --no-dev` leaves it: no Scribe classes and no
 * Scribe service provider. Run in a child process by BootWithoutDevPackagesTest; prints JSON.
 *
 * Usage: php boot-without-scribe.php <packages-manifest-path> <services-manifest-path>
 */
use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Console\Kernel;
use Knuckles\Scribe\Config\Defaults;

$root = dirname(__DIR__, 2);

/** @var ClassLoader $loader */
$loader = require $root.'/vendor/autoload.php';

// Composer's loader answers for everything but Scribe, as if the package were not installed.
$loader->unregister();
spl_autoload_register(static function (string $class) use ($loader): void {
    if (! str_starts_with($class, 'Knuckles\\Scribe\\')) {
        $loader->loadClass($class);
    }
}, prepend: true);

// Package discovery as --no-dev builds it: without the development packages' providers.
putenv('APP_PACKAGES_CACHE='.$argv[1]);
putenv('APP_SERVICES_CACHE='.$argv[2]);

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo json_encode([
    'booted' => $app->isBooted(),
    'scribe_loaded' => class_exists(Defaults::class),
    'scribe_config' => config('scribe'),
], JSON_THROW_ON_ERROR);
