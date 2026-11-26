<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Models\PendingUpload;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Images;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    $this->user = User::factory()->create(['avatar_url' => 'https://lh3.googleusercontent.com/a/photo']);
    Sanctum::actingAs($this->user);
});

it('shows the Google or Apple picture until one is uploaded', function (): void {
    $this->getJson('/api/v1/me')->assertJsonPath('data.avatar_url', 'https://lh3.googleusercontent.com/a/photo');
});

it('sets the uploaded picture as the account\'s, in its small size', function (): void {
    $this->put('/api/v1/me/avatar', ['file' => Images::upload(Images::jpegWithExif())], ['Accept' => 'application/json'])->assertOk();

    $avatar = $this->user->refresh()->getFirstMedia(User::AVATAR);
    expect($avatar)->toBeInstanceOf(Media::class);
    $url = Storage::disk('public')->url($avatar->getPathRelativeToRoot('thumb'));

    $this->getJson('/api/v1/me')->assertJsonPath('data.avatar_url', $url);
});

it('replaces the previous picture and its files', function (): void {
    $this->put('/api/v1/me/avatar', ['file' => Images::upload(Images::jpeg())])->assertOk();
    $first = Media::query()->sole();

    $this->put('/api/v1/me/avatar', ['file' => Images::upload(Images::png())])->assertOk();

    expect(Media::query()->sole()->id)->not->toBe($first->id)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(2)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1)
        ->and(Storage::disk('public')->directories())->toBe([Media::query()->sole()->id])
        ->and(Storage::disk('local')->directories())->toBe([Media::query()->sole()->id]);
});

it('removes the picture, and the Google or Apple one shows again', function (): void {
    $this->put('/api/v1/me/avatar', ['file' => Images::upload(Images::jpeg())])->assertOk();

    $this->deleteJson('/api/v1/me/avatar')
        ->assertOk()
        ->assertJsonPath('data.avatar_url', 'https://lh3.googleusercontent.com/a/photo');

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuses what is not an image', function (): void {
    $this->put('/api/v1/me/avatar', ['file' => Images::upload('<svg/>', 'me.svg')], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed');
});

it('deletes the picture and the pending uploads with the account', function (): void {
    $this->put('/api/v1/me/avatar', ['file' => Images::upload(Images::jpeg())])->assertOk();
    $this->post('/api/v1/uploads', ['file' => Images::upload(Images::jpeg())])->assertCreated();
    $someoneElse = User::factory()->create();
    Sanctum::actingAs($someoneElse);
    $this->post('/api/v1/uploads', ['file' => Images::upload(Images::jpeg())])->assertCreated();

    Sanctum::actingAs($this->user);
    $this->deleteJson('/api/v1/me')->assertNoContent();

    expect(Media::query()->count())->toBe(1)
        ->and(PendingUpload::query()->sole()->user_id)->toBe($someoneElse->id)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(2)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
});
