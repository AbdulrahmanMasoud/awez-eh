<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Media\Actions\StoreUpload;
use App\Domains\Media\Models\PendingUpload;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Images;
use Tests\TestCase;

// Invariant 1 for properties: resident B can neither read A's unpublished property, nor change,
// move or photograph it, nor use A's uploads. Each request carries a real token.

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    $this->alice = User::factory()->withPhone()->create();
    $this->bob = User::factory()->withPhone()->create();
    $this->aliceToken = $this->alice->createToken('Pixel 8')->plainTextToken;
    $this->bobToken = $this->bob->createToken('iPhone')->plainTextToken;
});

function asResident(string $token): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

it('hides A\'s unpublished property from B, as if it did not exist', function (ContentStatus $status): void {
    $property = Property::factory()->for($this->alice)->inStatus($status)->create();

    asResident($this->bobToken)->getJson("/api/v1/properties/{$property->id}")->assertNotFound();
    asResident($this->bobToken)->patchJson("/api/v1/properties/{$property->id}", ['title' => 'عنوان بتاع بوب'])->assertNotFound();
    asResident($this->bobToken)->postJson("/api/v1/properties/{$property->id}/archive")->assertNotFound();
    asResident($this->bobToken)->postJson("/api/v1/properties/{$property->id}/contact")->assertNotFound();
    asResident($this->bobToken)->getJson('/api/v1/me/properties')->assertJsonCount(0, 'data');

    asResident($this->aliceToken)->getJson("/api/v1/properties/{$property->id}")->assertOk();
})->with([ContentStatus::Draft, ContentStatus::PendingReview, ContentStatus::Rejected, ContentStatus::Paused]);

it('lets B see A\'s published property but not change or move it', function (string $method, string $path, array $body): void {
    $property = Property::factory()->for($this->alice)->published()->state(['purpose' => PropertyPurpose::Sale, 'rent_period' => null])->create();
    $before = $property->only(['title', 'status', 'price']);

    asResident($this->bobToken)->json($method, str_replace('{id}', $property->id, $path), $body)
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');

    expect($property->refresh()->only(['title', 'status', 'price']))->toEqual($before);
})->with([
    'edit' => ['PATCH', '/api/v1/properties/{id}', ['title' => 'عنوان بتاع بوب']],
    'submit' => ['POST', '/api/v1/properties/{id}/submit', []],
    'pause' => ['POST', '/api/v1/properties/{id}/pause', []],
    'resume' => ['POST', '/api/v1/properties/{id}/resume', []],
    'sold' => ['POST', '/api/v1/properties/{id}/sold', []],
    'rented' => ['POST', '/api/v1/properties/{id}/rented', []],
    'archive' => ['POST', '/api/v1/properties/{id}/archive', []],
]);

it('refuses A\'s uploads to B, when listing and when editing', function (): void {
    $alicesUpload = app(StoreUpload::class)->handle($this->alice, Images::file(Images::jpeg()))->id;
    $bobs = Property::factory()->for($this->bob)->inStatus(ContentStatus::Draft)->create();

    asResident($this->bobToken)->patchJson("/api/v1/properties/{$bobs->id}", ['photos' => [$alicesUpload]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'upload_not_available');

    expect(PendingUpload::query()->whereKey($alicesUpload)->exists())->toBeTrue()
        ->and($bobs->refresh()->getMedia('photos'))->toHaveCount(0);
});

it('refuses A\'s property photos as B\'s, so they cannot be moved between listings', function (): void {
    $alices = Property::factory()->for($this->alice)->inStatus(ContentStatus::Draft)->create();
    $upload = app(StoreUpload::class)->handle($this->alice, Images::file(Images::jpeg()))->id;
    asResident($this->aliceToken)->patchJson("/api/v1/properties/{$alices->id}", ['photos' => [$upload]])->assertOk();
    $photo = $alices->refresh()->getFirstMedia('photos')?->id;
    $bobs = Property::factory()->for($this->bob)->inStatus(ContentStatus::Draft)->create();

    asResident($this->bobToken)->patchJson("/api/v1/properties/{$bobs->id}", ['photos' => [$photo]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'upload_not_available');

    expect($alices->refresh()->getFirstMedia('photos')?->id)->toBe($photo);
});

it('asks a guest to sign in for everything but reading', function (string $method, string $path): void {
    $property = Property::factory()->for($this->alice)->published()->create();

    $this->json($method, str_replace('{id}', $property->id, $path))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
})->with([
    'list' => ['POST', '/api/v1/properties'],
    'edit' => ['PATCH', '/api/v1/properties/{id}'],
    'submit' => ['POST', '/api/v1/properties/{id}/submit'],
    'archive' => ['POST', '/api/v1/properties/{id}/archive'],
    'contact' => ['POST', '/api/v1/properties/{id}/contact'],
    'own list' => ['GET', '/api/v1/me/properties'],
]);
