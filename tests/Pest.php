<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
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

/**
 * Every Eloquent model under app/, found from its file as PSR-4 maps it.
 *
 * @return list<class-string<Model>>
 */
function modelClasses(): array
{
    $root = __DIR__.'/../app';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $models = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $class = 'App\\'.str_replace('/', '\\', substr($file->getPathname(), strlen($root) + 1, -strlen('.php')));

        if (class_exists($class) && is_subclass_of($class, Model::class) && ! new ReflectionClass($class)->isAbstract()) {
            $models[] = $class;
        }
    }

    sort($models);

    return $models;
}

/**
 * Signs in to the admin panel as an admin with these roles (a super_admin without roles) and
 * makes the panel the current one, as the panel's own middleware does for every request.
 */
function actingAsAdmin(AdminRole ...$roles): User
{
    $admin = User::factory()->admin(...$roles)->create();

    test()->actingAs($admin);
    Filament::setCurrentPanel('admin');

    return $admin;
}
