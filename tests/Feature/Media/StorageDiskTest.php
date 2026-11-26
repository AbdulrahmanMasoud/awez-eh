<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Media\Models\Media;
use App\Domains\RealEstate\Models\PropertyAgreement;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Images;

it('puts photos\' sizes on the disk MEDIA_DISK names and their originals on MEDIA_PRIVATE_DISK, R2 included', function (): void {
    config(['awez.media.disk' => 'r2', 'awez.media.private_disk' => 'r2_private']);
    // Local fakes under R2's names: the S3 driver itself is never called.
    Storage::fake('r2');
    Storage::fake('r2_private');
    Storage::fake('public');
    Storage::fake('local');
    Sanctum::actingAs(User::factory()->create());

    $id = $this->post('/api/v1/uploads', ['file' => Images::upload(Images::jpeg())])->assertCreated()->json('data.id');

    $media = Media::query()->sole();

    expect($media->disk)->toBe('r2_private')
        ->and($media->conversions_disk)->toBe('r2')
        ->and(Storage::disk('r2_private')->allFiles())->toHaveCount(1)
        ->and(Storage::disk('r2')->allFiles())->toHaveCount(2)
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(Storage::disk('local')->allFiles())->toBe([]);

    $this->getJson("/api/v1/uploads/{$id}")
        ->assertJsonPath('data.thumb_url', Storage::disk('r2')->url($media->getPathRelativeToRoot('thumb')));
});

it('puts private documents on the disk MEDIA_PRIVATE_DISK names', function (): void {
    config(['awez.media.private_disk' => 'r2_private']);
    Storage::fake('r2_private');
    Storage::fake('local');

    $media = PropertyAgreement::factory()->create()
        ->addMedia(Images::file(Images::jpeg()))
        ->toMediaCollection('documents');

    expect($media->disk)->toBe('r2_private')
        ->and(Storage::disk('r2_private')->allFiles())->toHaveCount(1)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('configures both R2 disks on the S3 driver, unused until .env names them', function (): void {
    expect(config('filesystems.disks.r2.driver'))->toBe('s3')
        ->and(config('filesystems.disks.r2.region'))->toBe('auto')
        ->and(config('filesystems.disks.r2_private.driver'))->toBe('s3')
        ->and(config('filesystems.disks.r2_private'))->not->toHaveKey('url')
        ->and(config('awez.media.disk'))->toBe('public')
        ->and(config('awez.media.private_disk'))->toBe('local');
});
