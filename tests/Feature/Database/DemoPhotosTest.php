<?php

declare(strict_types=1);

use App\Domains\Media\Actions\AddImage;
use App\Domains\Media\Models\Media;
use App\Domains\RealEstate\Models\Property;
use Database\Seeders\DemoPhotosSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

it('gives each demo property three drawn photos through the real path, once', function (): void {
    $properties = Property::factory()->count(2)->published()->create();

    $this->seed(DemoPhotosSeeder::class);
    $this->seed(DemoPhotosSeeder::class);

    foreach ($properties as $property) {
        $photos = $property->refresh()->getMedia(Property::PHOTOS);

        expect($photos)->toHaveCount(DemoPhotosSeeder::PHOTOS_PER_PROPERTY)
            ->and($photos->every(fn (Media $media): bool => $media->isProcessed() && $media->file_name === 'photo.jpg' && $media->width === 1600))->toBeTrue();
    }
});

it('refuses to draw demo photos outside local and testing', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(DemoPhotosSeeder::class)->run(app(AddImage::class)))->toThrow(LogicException::class);

    app()->detectEnvironment(fn (): string => 'testing');
});
