<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * RealEstateSettings' defaults (task 008's brief). Admins change them in the panel afterwards.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('real_estate', function (SettingsBlueprint $blueprint): void {
            $blueprint->add('active_listings_per_account', 10);
            $blueprint->add('new_listings_per_day', 5);
        });
    }

    public function down(): void
    {
        $this->migrator->inGroup('real_estate', function (SettingsBlueprint $blueprint): void {
            $blueprint->delete('active_listings_per_account');
            $blueprint->delete('new_listings_per_day');
        });
    }
};
