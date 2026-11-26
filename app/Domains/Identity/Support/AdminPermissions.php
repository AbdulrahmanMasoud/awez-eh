<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Enums\AdminRole;

/**
 * The admin permissions and what each role starts with. Names follow Filament Shield's
 * convention (`Affix:Subject`, PascalCase), so its role screen lists the same keys; a test keeps
 * this list equal to Shield's catalogue. `super_admin` holds every permission explicitly: no
 * gate shortcut, which would also open the API (DECISIONS 86).
 */
final class AdminPermissions
{
    /** Reference data: a list, a record, create, edit and reorder. Nothing is ever deleted (DECISIONS 42). */
    private const array REFERENCE_DATA_ABILITIES = ['ViewAny', 'View', 'Create', 'Update', 'Reorder'];

    private const array REFERENCE_DATA = ['City', 'District', 'Trade', 'PlaceCategory'];

    private const array USER_ABILITIES = ['ViewAny', 'View', 'Suspend', 'Reactivate', 'UpdateRoles', 'RevealPhone'];

    private const array ROLE_ABILITIES = ['ViewAny', 'View', 'Create', 'Update', 'Delete'];

    public const string ACTIVITY_LOG = 'View:ActivityLog';

    /** إعدادات الصور: limits on photos and uploads (MediaSettings). */
    public const string IMAGE_SETTINGS = 'View:ImageSettings';

    /**
     * Opening the scanned documents of brokerage agreements (signed URLs, each one logged). A
     * custom permission in Shield's config until the agreements' own screen comes (step 2.2).
     */
    public const string AGREEMENT_DOCUMENTS = 'ViewDocuments:PropertyAgreement';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            ...self::referenceData(),
            ...self::of('User', self::USER_ABILITIES),
            ...self::of('Role', self::ROLE_ABILITIES),
            self::ACTIVITY_LOG,
            self::IMAGE_SETTINGS,
            self::AGREEMENT_DOCUMENTS,
        ];
    }

    /**
     * What a role is given when it is seeded. Admins may change it on the roles screen; seeding
     * again adds what is missing and takes nothing away.
     *
     * @return list<string>
     */
    public static function defaultsFor(AdminRole $role): array
    {
        return match ($role) {
            AdminRole::SuperAdmin => self::all(),
            AdminRole::Moderator => self::of('User', ['ViewAny', 'View', 'Suspend', 'Reactivate', 'RevealPhone']),
            AdminRole::DataEntry => self::referenceData(),
            AdminRole::Sales => self::of('User', ['ViewAny', 'View']),
        };
    }

    public static function for(string $ability, string $subject): string
    {
        return "{$ability}:{$subject}";
    }

    /**
     * @return list<string>
     */
    private static function referenceData(): array
    {
        return array_merge(...array_map(
            fn (string $subject): array => self::of($subject, self::REFERENCE_DATA_ABILITIES),
            self::REFERENCE_DATA,
        ));
    }

    /**
     * @param  list<string>  $abilities
     * @return list<string>
     */
    private static function of(string $subject, array $abilities): array
    {
        return array_map(fn (string $ability): string => self::for($ability, $subject), $abilities);
    }
}
