<?php

declare(strict_types=1);

namespace App\Domains\Media\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Limits on photos and documents that a super admin tunes on إعدادات الصور. Defaults are in
 * database/settings. A collection of one (an avatar, a logo) is not a setting: it is one by nature.
 */
final class MediaSettings extends Settings
{
    /** The largest file accepted, before it is cleaned. */
    public int $max_file_size_mb;

    /** Uploads a resident may send in an hour (photos and avatars together). */
    public int $uploads_per_hour;

    public int $property_photos;

    public int $agreement_documents;

    public int $provider_work_photos;

    public int $place_photos;

    public int $used_item_photos;

    public int $request_photos;

    public static function group(): string
    {
        return 'media';
    }

    /**
     * Every value by name, as the settings page shows them and the log compares them.
     *
     * @return array<string, int>
     */
    public function values(): array
    {
        return [
            'max_file_size_mb' => $this->max_file_size_mb,
            'uploads_per_hour' => $this->uploads_per_hour,
            'property_photos' => $this->property_photos,
            'agreement_documents' => $this->agreement_documents,
            'provider_work_photos' => $this->provider_work_photos,
            'place_photos' => $this->place_photos,
            'used_item_photos' => $this->used_item_photos,
            'request_photos' => $this->request_photos,
        ];
    }

    public function maxFileSizeKilobytes(): int
    {
        return $this->max_file_size_mb * 1024;
    }
}
