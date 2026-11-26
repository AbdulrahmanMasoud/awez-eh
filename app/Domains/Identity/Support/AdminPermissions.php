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

    /**
     * Properties in the panel: the list and a property's page, creating a brokered one (sales),
     * approving and rejecting (moderators; rejecting covers taking down), recording an agreement
     * (sales), and seeing a brokered property's owner number (sales).
     */
    private const array PROPERTY_ABILITIES = ['ViewAny', 'View', 'Create', 'Approve', 'Reject', 'MarkBrokered', 'RevealOwnerPhone'];

    /** Agreements, through the property's page: listed, opened, recorded; never edited or deleted. */
    private const array AGREEMENT_ABILITIES = ['ViewAny', 'View', 'Create'];

    /** المراجعة: the queue of everything waiting for review. */
    public const string REVIEW_QUEUE = 'View:ReviewQueue';

    /** إعدادات العقارات: the limits on residents' listings (RealEstateSettings). */
    public const string REAL_ESTATE_SETTINGS = 'View:RealEstateSettings';

    public const string ACTIVITY_LOG = 'View:ActivityLog';

    /** إعدادات الصور: limits on photos and uploads (MediaSettings). */
    public const string IMAGE_SETTINGS = 'View:ImageSettings';

    /**
     * Opening the scanned documents of brokerage agreements (signed URLs, each one logged). A
     * custom permission in Shield's config until the agreements' own screen comes (step 2.2).
     * Sales holds it by default: they bring the deals the agreements cover (DECISIONS 106).
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
            ...self::of('Property', self::PROPERTY_ABILITIES),
            ...self::of('PropertyAgreement', self::AGREEMENT_ABILITIES),
            self::ACTIVITY_LOG,
            self::IMAGE_SETTINGS,
            self::AGREEMENT_DOCUMENTS,
            self::REVIEW_QUEUE,
            self::REAL_ESTATE_SETTINGS,
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
            AdminRole::Moderator => [
                ...self::of('User', ['ViewAny', 'View', 'Suspend', 'Reactivate', 'RevealPhone']),
                ...self::of('Property', ['ViewAny', 'View', 'Approve', 'Reject']),
                self::REVIEW_QUEUE,
            ],
            AdminRole::DataEntry => self::referenceData(),
            AdminRole::Sales => [
                ...self::of('User', ['ViewAny', 'View']),
                ...self::of('Property', ['ViewAny', 'View', 'Create', 'MarkBrokered', 'RevealOwnerPhone']),
                ...self::of('PropertyAgreement', self::AGREEMENT_ABILITIES),
                self::AGREEMENT_DOCUMENTS,
            ],
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
