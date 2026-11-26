<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Media\Jobs\DeleteExpiredUploads;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Models\PendingUpload;
use App\Domains\Media\Settings\MediaSettings;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Tests\Support\Images;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

/**
 * Files on the two disks: a photo is one private original and two public sizes.
 *
 * @return array{local: int, public: int}
 */
function mediaFileCounts(): array
{
    return ['local' => count(Storage::disk('local')->allFiles()), 'public' => count(Storage::disk('public')->allFiles())];
}

function upload(User $user): string
{
    Sanctum::actingAs($user);

    return test()->post('/api/v1/uploads', ['file' => Images::upload(Images::jpeg(1200, 900))])->assertCreated()->json('data.id');
}

it('answers with the photo\'s shape while its sizes are made on the media queue, then with both URLs', function (): void {
    Date::setTestNow('2026-11-26 10:00:00');
    Queue::fake();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $id = $this->post('/api/v1/uploads', ['file' => Images::upload(Images::jpeg(1200, 900))])
        ->assertCreated()
        ->assertExactJson(['data' => [
            'id' => PendingUpload::query()->sole()->id,
            'thumb_url' => null,
            'large_url' => null,
            'width' => 1200,
            'height' => 900,
            'processing' => true,
            'expires_at' => '2026-11-27T10:00:00Z',
        ]])
        ->json('data.id');

    Queue::assertPushedOn('media', PerformConversionsJob::class);

    $this->getJson("/api/v1/uploads/{$id}")->assertOk()->assertJsonPath('data.processing', true);

    // The queue runs Medialibrary's job, twice: the second run makes the same files.
    $media = Media::query()->sole();
    $job = Queue::pushed(PerformConversionsJob::class)->sole();
    app()->call($job->handle(...));
    $first = Storage::disk('public')->get($media->getPathRelativeToRoot('thumb'));
    app()->call($job->handle(...));

    expect(Storage::disk('public')->get($media->getPathRelativeToRoot('thumb')))->toBe($first)
        ->and(mediaFileCounts())->toBe(['local' => 1, 'public' => 2]);

    $this->getJson("/api/v1/uploads/{$id}")
        ->assertOk()
        ->assertJsonPath('data.processing', false)
        ->assertJsonPath('data.thumb_url', Storage::disk('public')->url($media->getPathRelativeToRoot('thumb')))
        ->assertJsonPath('data.large_url', Storage::disk('public')->url($media->getPathRelativeToRoot('large')));
});

it('never gives the original a URL', function (): void {
    $id = upload(User::factory()->create());

    $json = $this->getJson("/api/v1/uploads/{$id}")->assertOk()->getContent();

    expect($json)->not->toContain('photo.jpg')
        ->and($json)->not->toContain('original');
});

it('shows and deletes an upload for its uploader only (invariant 1)', function (): void {
    $owner = User::factory()->create();
    $id = upload($owner);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/uploads/{$id}")->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->deleteJson("/api/v1/uploads/{$id}")->assertNotFound();

    expect(PendingUpload::query()->whereKey($id)->exists())->toBeTrue();

    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/uploads/{$id}")->assertOk();
});

it('deletes an upload with its files', function (): void {
    $id = upload(User::factory()->create());
    expect(mediaFileCounts())->toBe(['local' => 1, 'public' => 2]);

    $this->deleteJson("/api/v1/uploads/{$id}")->assertNoContent();

    expect(PendingUpload::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0)
        ->and(mediaFileCounts())->toBe(['local' => 0, 'public' => 0]);
});

it('forgets an upload after 24 hours, even before the cleanup', function (): void {
    Date::setTestNow('2026-11-26 10:00:00');
    $id = upload(User::factory()->create());

    Date::setTestNow('2026-11-27 09:59:59');
    $this->getJson("/api/v1/uploads/{$id}")->assertOk();

    Date::setTestNow('2026-11-27 10:00:00');
    $this->getJson("/api/v1/uploads/{$id}")->assertNotFound();
    $this->deleteJson("/api/v1/uploads/{$id}")->assertNotFound();
});

it('answers 404 for an id that is not an upload', function (string $id): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/uploads/{$id}")->assertNotFound()->assertJsonPath('code', 'not_found');
})->with(['not-a-uuid', '019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01']);

it('needs a signed-in account', function (): void {
    $this->postJson('/api/v1/uploads')->assertUnauthorized();
    $this->getJson('/api/v1/uploads/019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01')->assertUnauthorized();
});

it('limits uploads per account per hour, avatars included', function (): void {
    MediaSettings::fake(['uploads_per_hour' => 2]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->post('/api/v1/uploads', ['file' => Images::upload(Images::jpeg())])->assertCreated();
    $this->put('/api/v1/me/avatar', ['file' => Images::upload(Images::jpeg())], ['Accept' => 'application/json'])->assertOk();
    $this->post('/api/v1/uploads', ['file' => Images::upload(Images::jpeg())], ['Accept' => 'application/json'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests');

    // Another account has its own budget.
    Sanctum::actingAs(User::factory()->create());
    $this->post('/api/v1/uploads', ['file' => Images::upload(Images::jpeg())])->assertCreated();
});

it('deletes expired uploads with their files every hour, and a second run finds nothing to do', function (): void {
    Date::setTestNow('2026-11-26 10:00:00');
    $user = User::factory()->create();
    $expired = upload($user);
    $expiredToo = upload($user);

    Date::setTestNow('2026-11-27 09:00:00');
    $fresh = upload($user);

    Date::setTestNow('2026-11-27 10:30:00');
    dispatch_sync(new DeleteExpiredUploads);

    expect(PendingUpload::query()->pluck('id')->all())->toBe([$fresh])
        ->and(Media::query()->count())->toBe(1)
        ->and(mediaFileCounts())->toBe(['local' => 1, 'public' => 2]);

    dispatch_sync(new DeleteExpiredUploads);

    expect(PendingUpload::query()->pluck('id')->all())->toBe([$fresh])
        ->and(mediaFileCounts())->toBe(['local' => 1, 'public' => 2])
        ->and([$expired, $expiredToo])->each->not->toBe($fresh);
});

it('runs the cleanup every hour on the media queue', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->description, DeleteExpiredUploads::class));

    expect($events)->toHaveCount(1)
        ->and($events->sole()->expression)->toBe('0 * * * *')
        ->and(new DeleteExpiredUploads()->queue)->toBe('media');
});
