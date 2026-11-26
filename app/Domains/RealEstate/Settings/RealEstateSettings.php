<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Limits on residents' listings that a super admin tunes on إعدادات العقارات. Defaults are in
 * database/settings.
 */
final class RealEstateSettings extends Settings
{
    /** Listings one account may hold that are not sold, rented or archived. */
    public int $active_listings_per_account;

    /** Listings one account may create in 24 hours. */
    public int $new_listings_per_day;

    public static function group(): string
    {
        return 'real_estate';
    }

    /**
     * @return array<string, int>
     */
    public function values(): array
    {
        return [
            'active_listings_per_account' => $this->active_listings_per_account,
            'new_listings_per_day' => $this->new_listings_per_day,
        ];
    }
}
