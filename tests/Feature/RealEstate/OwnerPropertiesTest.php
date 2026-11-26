<?php

declare(strict_types=1);

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Actions\StoreUpload;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Models\PendingUpload;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Moderation\Support\ContentLog;
use App\Domains\RealEstate\Actions\CreateProperty;
use App\Domains\RealEstate\Data\NewProperty;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Enums\PropertyType;
use App\Domains\RealEstate\Enums\RentPeriod;
use App\Domains\RealEstate\Exceptions\TooManyActiveListings;
use App\Domains\RealEstate\Exceptions\TooManyNewListings;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Settings\RealEstateSettings;
use App\Support\Geo\Point;
use Brick\Money\Money;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Images;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    $this->district = District::factory()->for(City::factory())->create();
    $this->owner = User::factory()->withPhone()->create();
    Sanctum::actingAs($this->owner);
});

/**
 * @return list<string> ids of new pending uploads
 */
function uploadsOf(User $user, int $count): array
{
    return array_map(fn (): string => app(StoreUpload::class)->handle($user, Images::file(Images::jpeg()))->id, range(1, $count));
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newListing(array $overrides = []): array
{
    return [
        'purpose' => 'rent',
        'rent_period' => 'monthly',
        'type' => 'apartment',
        'title' => 'شقة للإيجار 3 غرف في الحي الثاني',
        'description' => 'الشقة في الدور التالت بأسانسير، قريبة من المدارس والمواصلات.',
        'price' => '6500.50',
        'area_m2' => 140,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'floor' => 3,
        'furnished' => false,
        'finishing' => 'finished',
        'district_id' => test()->district->id,
        'address_note' => 'بجوار مسجد الرحمن',
        'lat' => 30.370512,
        'lng' => 30.508531,
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $attributes
 */
function ownListing(ContentStatus $status = ContentStatus::Published, array $attributes = []): Property
{
    return Property::factory()->for(test()->owner)->inStatus($status)->create([
        'purpose' => PropertyPurpose::Rent,
        'rent_period' => RentPeriod::Monthly,
        'district_id' => test()->district->id,
        'city_id' => test()->district->city_id,
        ...$attributes,
    ]);
}

it('lists a property as a draft, with its photos in order and the exact pin', function (): void {
    $uploads = uploadsOf($this->owner, 2);
    $mediaIds = array_map(fn (string $id): ?string => PendingUpload::query()->findOrFail($id)->file()?->id, $uploads);

    $response = $this->postJson('/api/v1/properties', newListing(['photos' => [$uploads[1], $uploads[0]]]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.price', ['amount' => '6500.50', 'currency' => 'EGP'])
        ->assertJsonPath('data.location', ['lat' => 30.370512, 'lng' => 30.508531, 'exact' => true])
        ->assertJsonPath('data.allowed_actions', ['submit', 'archive'])
        ->assertJsonPath('data.brokered', false);

    $property = Property::query()->sole();

    expect($response->json('data.photos.*.id'))->toBe([$mediaIds[1], $mediaIds[0]])
        ->and($property->user_id)->toBe($this->owner->id)
        ->and($property->city_id)->toBe($this->district->city_id)
        ->and($property->price?->isEqualTo(Money::of('6500.50', 'EGP')))->toBeTrue()
        ->and(PendingUpload::query()->count())->toBe(0);
});

it('sends it straight to review with submit, never to the public', function (): void {
    $this->postJson('/api/v1/properties', newListing(['submit' => true]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_review')
        ->assertJsonPath('data.allowed_actions', ['archive']);

    $this->getJson('/api/v1/properties')->assertJsonCount(0, 'data');
});

it('never lets a resident set brokered, a status or an owner', function (): void {
    $someoneElse = User::factory()->create();

    $this->postJson('/api/v1/properties', newListing(['brokered' => true, 'status' => 'published', 'user_id' => $someoneElse->id, 'views_count' => 9000]))
        ->assertCreated();

    expect(Property::query()->sole()->only(['brokered', 'status', 'user_id', 'views_count']))
        ->toBe(['brokered' => false, 'status' => ContentStatus::Draft, 'user_id' => $this->owner->id, 'views_count' => 0]);
});

it('asks for a confirmed number before listing', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/properties', newListing())
        ->assertForbidden()
        ->assertJsonPath('code', 'phone_required');
});

it('checks the listing with Arabic field names', function (array $overrides, string $field, string $message): void {
    $this->postJson('/api/v1/properties', newListing($overrides))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath("errors.{$field}.0", $message);
})->with([
    'a rent with no period' => [['rent_period' => null], 'rent_period', 'حقل مدة الإيجار مطلوب في حال ما إذا كان الغرض يساوي rent.'],
    'a sale with a period' => [['purpose' => 'sale', 'rent_period' => 'monthly'], 'rent_period', 'حقل مدة الإيجار محظور ما لم يكن الغرض ضمن rent.'],
    'no pin' => [['lat' => null], 'lat', 'حقل خط العرض مطلوب.'],
    'a price with three decimals' => [['price' => '10.505'], 'price', 'يجب أن يحتوي الحقل السعر على 0-2 منزلة/منازل عشرية.'],
    'an unknown district' => [['district_id' => '019ac2f1-7a3e-7d10-9b2c-4e5f6a7b8c9d'], 'district_id', 'قيمة الحقل المنطقة غير موجودة.'],
]);

it('keeps to the account\'s limits: 10 open listings, 5 new a day', function (): void {
    Date::setTestNow('2026-11-27 09:00:00');
    Property::factory()->for($this->owner)->count(9)->inStatus(ContentStatus::Published)->create(['created_at' => '2026-11-20 09:00:00']);
    Property::factory()->for($this->owner)->count(3)->inStatus(ContentStatus::Sold)->create(['created_at' => '2026-11-27 08:00:00']);
    Property::factory()->for($this->owner)->inStatus(ContentStatus::Archived)->create(['created_at' => '2026-11-27 08:00:00']);

    $this->postJson('/api/v1/properties', newListing())->assertCreated();
    $this->postJson('/api/v1/properties', newListing())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'too_many_active_listings')
        ->assertJsonPath('errors.limit', 10);

    Property::query()->whereBelongsTo($this->owner)->where('status', ContentStatus::Published)->limit(5)->get()
        ->each(fn (Property $property) => $property->forceFill(['status' => ContentStatus::Archived])->save());

    // Five created in the last 24 hours: the new one, three sold and one archived.
    $this->postJson('/api/v1/properties', newListing())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'too_many_new_listings')
        ->assertJsonPath('errors.limit', 5);

    Date::setTestNow('2026-11-28 09:00:01');
    $this->postJson('/api/v1/properties', newListing())->assertCreated();
});

it('reads the limits from the settings an admin tunes', function (): void {
    $data = new NewProperty(
        purpose: PropertyPurpose::Sale,
        type: PropertyType::Land,
        title: 'أرض للبيع في المنطقة الصناعية',
        description: 'أرض مسورة على شارع رئيسي.',
        price: Money::of(800_000, 'EGP'),
        rentPeriod: null,
        areaM2: 600,
        bedrooms: null,
        bathrooms: null,
        floor: null,
        furnished: false,
        finishing: null,
        districtId: $this->district->id,
        addressNote: null,
        location: new Point(30.37, 30.51),
        photos: [],
        submit: false,
    );

    RealEstateSettings::fake(['active_listings_per_account' => 1, 'new_listings_per_day' => 5]);
    ownListing(ContentStatus::Draft);
    expect(fn () => app(CreateProperty::class)->handle($this->owner, $data))->toThrow(TooManyActiveListings::class);

    RealEstateSettings::fake(['active_listings_per_account' => 5, 'new_listings_per_day' => 1]);
    expect(fn () => app(CreateProperty::class)->handle($this->owner, $data))->toThrow(TooManyNewListings::class);

    RealEstateSettings::fake(['active_listings_per_account' => 5, 'new_listings_per_day' => 5]);
    expect(app(CreateProperty::class)->handle($this->owner, $data)->status)->toBe(ContentStatus::Draft);
});

it('refuses someone else\'s uploads, or more photos than the limit, and creates nothing', function (): void {
    $theirs = uploadsOf(User::factory()->create(), 1);

    $this->postJson('/api/v1/properties', newListing(['photos' => $theirs]))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'upload_not_available');

    expect(Property::query()->count())->toBe(0);
});

it('keeps a published property public when only the price changes', function (): void {
    $property = ownListing();

    $this->patchJson("/api/v1/properties/{$property->id}", ['price' => '7000'])
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.price.amount', '7000.00');

    $this->getJson('/api/v1/properties')->assertJsonPath('data.0.id', $property->id);
});

it('sends a published property back to review after any other change, hidden until approved', function (array $changes): void {
    $property = ownListing();

    $this->patchJson("/api/v1/properties/{$property->id}", $changes)
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_review');

    $this->getJson('/api/v1/properties')->assertJsonCount(0, 'data');

    $entry = Activity::query()->where('log_name', ContentLog::NAME)->sole();
    expect($entry->event)->toBe('edit')
        ->and($entry->attribute_changes?->toArray())->toBe(['old' => ['status' => 'published'], 'attributes' => ['status' => 'pending_review']]);
})->with([
    'the title' => [fn (): array => ['title' => 'شقة للإيجار 3 غرف بجوار الجامعة']],
    'the price and the description' => [fn (): array => ['price' => '7000', 'description' => 'تم تجديد المطبخ والحمامات بالكامل.']],
    'the pin' => [fn (): array => ['lat' => 30.38, 'lng' => 30.51]],
    'the district' => [fn (): array => ['district_id' => District::factory()->create()->id]],
]);

it('sends it back to review for a new photo, not for removing or reordering', function (): void {
    $property = ownListing();
    $first = uploadsOf($this->owner, 3);
    $this->patchJson("/api/v1/properties/{$property->id}", ['photos' => $first]);
    $property->refresh()->forceFill(['status' => ContentStatus::Published])->save();
    $held = $property->refresh()->getMedia('photos')->pluck('id')->all();

    $this->patchJson("/api/v1/properties/{$property->id}", ['photos' => [$held[2], $held[0]]])
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.photos.*.id', [$held[2], $held[0]]);

    expect(Media::query()->whereKey($held[1])->exists())->toBeFalse();

    [$new] = uploadsOf($this->owner, 1);
    $newMedia = PendingUpload::query()->findOrFail($new)->file()?->id;

    $this->patchJson("/api/v1/properties/{$property->id}", ['photos' => [$new, $held[2], $held[0]]])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_review')
        ->assertJsonPath('data.photos.*.id', [$newMedia, $held[2], $held[0]]);
});

it('deletes a removed photo\'s files from the disks', function (): void {
    $property = ownListing(ContentStatus::Draft);
    $this->patchJson("/api/v1/properties/{$property->id}", ['photos' => uploadsOf($this->owner, 2)])->assertOk();
    [$keep, $remove] = $property->refresh()->getMedia('photos')->all();

    $this->patchJson("/api/v1/properties/{$property->id}", ['photos' => [$keep->id]])->assertOk();

    $paths = [...Storage::disk('public')->allFiles(), ...Storage::disk('local')->allFiles()];
    expect(array_filter($paths, fn (string $path): bool => str_starts_with($path, $remove->id)))->toBe([])
        ->and(array_filter($paths, fn (string $path): bool => str_starts_with($path, $keep->id)))->not->toBe([]);
});

it('marks a paused property changed, so resuming it goes through review', function (): void {
    $property = ownListing(ContentStatus::Paused);

    $this->patchJson("/api/v1/properties/{$property->id}", ['title' => 'شقة للإيجار بعد التجديد'])
        ->assertOk()
        ->assertJsonPath('data.status', 'paused')
        ->assertJsonPath('data.changed_since_review', true);

    $this->postJson("/api/v1/properties/{$property->id}/resume")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_review');
});

it('lets a rejected property be submitted again after any edit, the price included', function (): void {
    $property = ownListing(ContentStatus::Rejected);

    $this->postJson("/api/v1/properties/{$property->id}/submit")
        ->assertConflict()
        ->assertJsonPath('code', 'action_not_allowed')
        ->assertJsonPath('errors.allowed_actions', ['archive']);

    $this->patchJson("/api/v1/properties/{$property->id}", ['price' => '5000'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.allowed_actions', ['submit', 'archive']);

    $this->postJson("/api/v1/properties/{$property->id}/submit")->assertOk()->assertJsonPath('data.status', 'pending_review');
});

it('changes nothing and logs nothing when the edit sends the same values', function (): void {
    $property = ownListing();

    $this->patchJson("/api/v1/properties/{$property->id}", ['title' => $property->title])->assertOk()->assertJsonPath('data.status', 'published');

    expect(Activity::query()->count())->toBe(0);
});

it('keeps a rent\'s period and refuses one for a sale', function (): void {
    $rent = ownListing();
    $sale = ownListing(attributes: ['purpose' => PropertyPurpose::Sale, 'rent_period' => null]);

    $this->patchJson("/api/v1/properties/{$rent->id}", ['rent_period' => null])->assertUnprocessable()->assertJsonValidationErrors('rent_period');
    $this->patchJson("/api/v1/properties/{$rent->id}", ['rent_period' => 'yearly'])->assertOk()->assertJsonPath('data.rent_period', 'yearly');
    $this->patchJson("/api/v1/properties/{$sale->id}", ['rent_period' => 'monthly'])->assertUnprocessable()->assertJsonValidationErrors('rent_period');
    $this->patchJson("/api/v1/properties/{$sale->id}", ['purpose' => 'rent'])->assertOk()->assertJsonPath('data.purpose', 'sale');
});

it('does not edit what is sold, rented or archived', function (ContentStatus $status): void {
    $property = ownListing($status);

    $this->patchJson("/api/v1/properties/{$property->id}", ['price' => '1'])
        ->assertConflict()
        ->assertJsonPath('code', 'not_editable');
})->with([ContentStatus::Sold, ContentStatus::Rented, ContentStatus::Archived]);

it('moves the owner\'s property through each endpoint', function (): void {
    $draft = ownListing(ContentStatus::Draft);
    $this->postJson("/api/v1/properties/{$draft->id}/submit")->assertOk()->assertJsonPath('data.status', 'pending_review');

    $published = ownListing();
    $this->postJson("/api/v1/properties/{$published->id}/pause")->assertOk()->assertJsonPath('data.status', 'paused');
    $this->postJson("/api/v1/properties/{$published->id}/resume")->assertOk()->assertJsonPath('data.status', 'published');
    $this->postJson("/api/v1/properties/{$published->id}/sold")->assertConflict()->assertJsonPath('errors.allowed_actions', ['pause', 'mark_rented', 'archive']);
    $this->postJson("/api/v1/properties/{$published->id}/rented")->assertOk()->assertJsonPath('data.status', 'rented')->assertJsonPath('data.editable', false);

    $sale = ownListing(attributes: ['purpose' => PropertyPurpose::Sale, 'rent_period' => null]);
    $this->postJson("/api/v1/properties/{$sale->id}/sold")->assertOk()->assertJsonPath('data.status', 'sold');

    $this->postJson("/api/v1/properties/{$draft->id}/archive")->assertOk()->assertJsonPath('data.status', 'archived')->assertJsonPath('data.allowed_actions', []);
});

it('lists the owner\'s own properties in every status, with the reason and what comes next', function (): void {
    $rejected = ownListing(ContentStatus::Rejected, ['rejection_reason' => 'الصور مش واضحة.']);
    $published = ownListing(ContentStatus::Published);
    Property::factory()->published()->create();

    $response = $this->getJson('/api/v1/me/properties')->assertOk();

    expect($response->json('data.*.id'))->toBe([$published->id, $rejected->id])
        ->and($response->json('data.1.rejection_reason'))->toBe('الصور مش واضحة.')
        ->and($response->json('data.0.allowed_actions'))->toBe(['pause', 'mark_rented', 'archive'])
        ->and($response->json('data.1.allowed_actions'))->toBe(['archive']);
});
