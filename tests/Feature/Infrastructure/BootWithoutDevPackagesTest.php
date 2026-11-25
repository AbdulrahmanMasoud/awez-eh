<?php

declare(strict_types=1);

use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

// Production installs with --no-dev (step 4.4): every config file still loads, so none may need
// a development package's classes. Scribe's config once did, and the app crashed at boot.
it('boots without Scribe, as a production install has none', function (): void {
    $directory = storage_path('framework/testing/no-dev-'.Str::random(8));
    File::ensureDirectoryExists($directory);

    // The discovered packages, less the development ones, as package:discover writes them under --no-dev.
    $packages = array_diff_key(app(PackageManifest::class)->manifest, array_flip(['knuckleswtf/scribe', 'laravel/boost', 'laravel/pail', 'laravel/sail', 'laravel/pao', 'nunomaduro/collision', 'pestphp/pest-plugin-laravel']));
    File::put("{$directory}/packages.php", '<?php return '.var_export($packages, true).';');

    $result = Process::path(base_path())
        ->env(['APP_ENV' => 'production', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('k', 32)), 'AWEZ_PHONE_VERIFICATION_DRIVER' => 'whatsapp_inbound'])
        ->run([PHP_BINARY, 'tests/Fixtures/boot-without-scribe.php', "{$directory}/packages.php", "{$directory}/services.php"]);

    File::deleteDirectory($directory);

    expect($result->successful())->toBeTrue($result->errorOutput().$result->output())
        ->and(json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR))->toBe([
            'booted' => true,
            'scribe_loaded' => false,
            'scribe_config' => [],
        ]);
});
