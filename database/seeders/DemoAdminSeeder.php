<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Actions\CreateAdmin;
use App\Domains\Identity\Enums\AdminRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use LogicException;

/**
 * The one admin with a known password, for local development and tests: never anywhere else.
 * It still sets up two-factor authentication on its first sign-in. Real admins come from
 * `awez:create-admin`.
 */
final class DemoAdminSeeder extends Seeder
{
    public const string EMAIL = 'admin@awez.test';

    public const string PASSWORD = 'awez-admin-demo';

    public function run(CreateAdmin $createAdmin): void
    {
        if (! App::environment(DemoContentSeeder::ENVIRONMENTS)) {
            throw new LogicException('The demo admin is for local and testing only.');
        }

        $createAdmin->handle('أدمن تجريبي', self::EMAIL, self::PASSWORD, AdminRole::SuperAdmin);
    }
}
