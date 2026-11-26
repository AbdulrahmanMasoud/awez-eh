<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Support\AdminPermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Makes sure every admin role and permission exists, and each role holds at least its defaults.
 * Idempotent; it only adds, so what a super_admin changed on the roles screen stays.
 */
final readonly class SyncAdminRoles
{
    private const string GUARD = 'web';

    public function __construct(private PermissionRegistrar $registrar) {}

    public function handle(): void
    {
        foreach (AdminPermissions::all() as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        foreach (AdminRole::cases() as $adminRole) {
            Role::findOrCreate($adminRole->value, self::GUARD)->givePermissionTo(AdminPermissions::defaultsFor($adminRole));
        }

        $this->registrar->forgetCachedPermissions();
    }
}
