<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * MediaSettings' defaults (task 007's brief). Admins change them in the panel afterwards; this
 * migration never runs twice, so it never undoes their changes.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('media', function (SettingsBlueprint $blueprint): void {
            $blueprint->add('max_file_size_mb', 10);
            $blueprint->add('uploads_per_hour', 60);
            $blueprint->add('property_photos', 20);
            $blueprint->add('agreement_documents', 5);
            $blueprint->add('provider_work_photos', 30);
            $blueprint->add('place_photos', 20);
            $blueprint->add('used_item_photos', 10);
            $blueprint->add('request_photos', 3);
        });
    }

    public function down(): void
    {
        $this->migrator->inGroup('media', function (SettingsBlueprint $blueprint): void {
            foreach (['max_file_size_mb', 'uploads_per_hour', 'property_photos', 'agreement_documents', 'provider_work_photos', 'place_photos', 'used_item_photos', 'request_photos'] as $name) {
                $blueprint->delete($name);
            }
        });
    }
};
