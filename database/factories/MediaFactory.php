<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Support\MediaDisks;
use App\Domains\Media\Support\MediaTypes;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A media row without a file, for tests that read rows only (an account's avatar by default).
 * Tests of files store real images through AddImage.
 *
 * @extends Factory<Media>
 */
final class MediaFactory extends Factory
{
    #[\Override]
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'model_type' => new User()->getMorphClass(),
            'model_id' => User::factory(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => User::AVATAR,
            'name' => 'photo',
            'file_name' => 'photo.jpg',
            'mime_type' => MediaTypes::JPEG,
            // A photo: its original private, its sizes public.
            'disk' => MediaDisks::private(),
            'conversions_disk' => MediaDisks::public(),
            'size' => 120_000,
            'width' => 1600,
            'height' => 1200,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ];
    }
}
