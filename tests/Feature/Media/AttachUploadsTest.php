<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Media\Actions\AttachUploads;
use App\Domains\Media\Actions\StoreUpload;
use App\Domains\Media\Exceptions\TooManyFiles;
use App\Domains\Media\Exceptions\UploadNotAvailable;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Models\PendingUpload;
use App\Domains\Media\Settings\MediaSettings;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Images;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    $this->user = User::factory()->create();
});

/**
 * @return list<string> the ids of new pending uploads of this user
 */
function pendingUploads(User $user, int $count): array
{
    return array_map(
        fn (): string => app(StoreUpload::class)->handle($user, Images::file(Images::jpeg()))->id,
        range(1, $count),
    );
}

it('moves the uploads to a property in the order given, keeping their files and sizes', function (): void {
    $property = Property::factory()->for($this->user)->create();
    $ids = pendingUploads($this->user, 3);
    $mediaIds = array_map(fn (string $id): string => PendingUpload::query()->findOrFail($id)->file()?->id ?? '', $ids);
    $files = [...Storage::disk('local')->allFiles(), ...Storage::disk('public')->allFiles()];

    app(AttachUploads::class)->handle($property, 'photos', $this->user, [$ids[2], $ids[0], $ids[1]]);

    $photos = $property->getMedia('photos');

    expect($photos->pluck('id')->all())->toBe([$mediaIds[2], $mediaIds[0], $mediaIds[1]])
        ->and($photos->pluck('order_column')->all())->toBe([1, 2, 3])
        ->and($photos->every(fn (Media $media): bool => $media->isProcessed()))->toBeTrue()
        ->and(PendingUpload::query()->count())->toBe(0)
        // The same files, where they were: nothing copied, nothing deleted.
        ->and([...Storage::disk('local')->allFiles(), ...Storage::disk('public')->allFiles()])->toBe($files);
});

it('adds after what the collection already holds', function (): void {
    $item = UsedItem::factory()->for($this->user)->create();
    $first = pendingUploads($this->user, 2);
    app(AttachUploads::class)->handle($item, 'photos', $this->user, $first);

    $second = pendingUploads($this->user, 1);
    app(AttachUploads::class)->handle($item, 'photos', $this->user, $second);

    expect($item->getMedia('photos')->pluck('order_column')->all())->toBe([1, 2, 3]);
});

it('refuses more files than the collection holds, attaching none', function (): void {
    MediaSettings::fake(['used_item_photos' => 2]);
    $item = UsedItem::factory()->for($this->user)->create();
    $ids = pendingUploads($this->user, 3);

    expect(fn () => app(AttachUploads::class)->handle($item, 'photos', $this->user, $ids))
        ->toThrow(function (TooManyFiles $exception): void {
            expect($exception->errors())->toBe(['limit' => 2]);
        });

    expect($item->getMedia('photos'))->toBeEmpty()
        ->and(PendingUpload::query()->count())->toBe(3);
});

it('refuses someone else\'s upload, and attaches nothing of the rest', function (): void {
    $property = Property::factory()->for($this->user)->create();
    $mine = pendingUploads($this->user, 1);
    $theirs = pendingUploads(User::factory()->create(), 1);

    expect(fn () => app(AttachUploads::class)->handle($property, 'photos', $this->user, [...$mine, ...$theirs]))
        ->toThrow(UploadNotAvailable::class);

    expect($property->getMedia('photos'))->toBeEmpty()
        ->and(PendingUpload::query()->count())->toBe(2);
});

it('refuses an expired upload', function (): void {
    Date::setTestNow('2026-11-26 10:00:00');
    $property = Property::factory()->for($this->user)->create();
    $ids = pendingUploads($this->user, 1);

    Date::setTestNow('2026-11-27 10:00:00');

    expect(fn () => app(AttachUploads::class)->handle($property, 'photos', $this->user, $ids))
        ->toThrow(UploadNotAvailable::class);
});

it('refuses an id that is no upload', function (string $id): void {
    $property = Property::factory()->for($this->user)->create();

    expect(fn () => app(AttachUploads::class)->handle($property, 'photos', $this->user, [$id]))
        ->toThrow(UploadNotAvailable::class);
})->with(['not-a-uuid', '019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01']);

it('rolls everything back when one row fails', function (): void {
    $property = Property::factory()->for($this->user)->create();
    $ids = pendingUploads($this->user, 2);
    // The second upload lost its file: the first must not have moved either.
    PendingUpload::query()->findOrFail($ids[1])->file()?->deleteQuietly();

    expect(fn () => app(AttachUploads::class)->handle($property, 'photos', $this->user, $ids))
        ->toThrow(UploadNotAvailable::class);

    expect($property->getMedia('photos'))->toBeEmpty()
        ->and(PendingUpload::query()->findOrFail($ids[0])->file())->not->toBeNull();
});

it('does nothing for no uploads, and counts one id once', function (): void {
    $property = Property::factory()->for($this->user)->create();
    $ids = pendingUploads($this->user, 1);

    app(AttachUploads::class)->handle($property, 'photos', $this->user, []);
    app(AttachUploads::class)->handle($property, 'photos', $this->user, [$ids[0], $ids[0]]);

    expect($property->getMedia('photos'))->toHaveCount(1);
});

it('knows every collection\'s limit from the settings, and one for a single file', function (): void {
    MediaSettings::fake(['property_photos' => 12, 'used_item_photos' => 4]);

    expect(Property::factory()->create()->mediaLimit('photos'))->toBe(12)
        ->and(UsedItem::factory()->create()->mediaLimit('photos'))->toBe(4)
        ->and(User::factory()->create()->mediaLimit(User::AVATAR))->toBe(1)
        ->and(fn () => Property::factory()->create()->mediaLimit('videos'))->toThrow(InvalidArgumentException::class);
});
