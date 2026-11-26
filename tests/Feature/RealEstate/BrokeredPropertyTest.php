<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Support\PhoneNumber;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->resident = User::factory()->withPhone()->create();
});

it('gives a signed-in resident the owner\'s number and a WhatsApp link', function (): void {
    $owner = User::factory()->withPhone('01012345678')->create();
    $property = Property::factory()->for($owner)->published()->create();
    Sanctum::actingAs($this->resident);

    $this->postJson("/api/v1/properties/{$property->id}/contact")
        ->assertOk()
        ->assertExactJson(['data' => ['phone' => '+201012345678', 'whatsapp_url' => 'https://wa.me/201012345678']]);
});

it('sends a brokered property\'s visitors to the platform instead', function (): void {
    $property = Property::factory()->for(User::factory()->withPhone())->published()->brokered()->create();
    Sanctum::actingAs($this->resident);

    $this->postJson("/api/v1/properties/{$property->id}/contact")
        ->assertConflict()
        ->assertJsonPath('code', 'contact_through_platform')
        ->assertJsonPath('message', 'العقار ده بوساطة المنصة: كلّمنا من الشات أو احجز معاينة.');
});

it('says so when the owner has no number any more', function (): void {
    $property = Property::factory()->for(User::factory())->published()->create();
    Sanctum::actingAs($this->resident);

    $this->postJson("/api/v1/properties/{$property->id}/contact")
        ->assertConflict()
        ->assertJsonPath('code', 'contact_unavailable');
});

it('limits asking for numbers to 30 an hour per account', function (): void {
    $property = Property::factory()->for(User::factory()->withPhone())->published()->create();
    Sanctum::actingAs($this->resident);

    foreach (range(1, 30) as $ignored) {
        $this->postJson("/api/v1/properties/{$property->id}/contact")->assertOk();
    }

    $this->postJson("/api/v1/properties/{$property->id}/contact")
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests');
});

it('never shows a brokered owner\'s number, id, email or name on any property endpoint', function (): void {
    $owner = User::factory()->withPhone('01098765432')->create(['name' => 'صاحب العقار الحقيقي', 'email' => 'real.owner@example.com']);
    $brokered = Property::factory()->for($owner)->published()->brokered()->state(['purpose' => PropertyPurpose::Sale, 'rent_period' => null])->create();
    PropertyAgreement::factory()->for($brokered)->create(['owner_name' => 'صاحب العقار الحقيقي', 'owner_phone' => PhoneNumber::from('01098765432')]);
    $phone = PhoneNumber::from('01098765432');
    $traces = [$phone->e164(), ltrim($phone->e164(), '+'), $phone->national(), '1098765432', $owner->id, 'real.owner@example.com', 'صاحب العقار الحقيقي'];

    $responses = [
        'list as a guest' => $this->getJson('/api/v1/properties'),
        'list sorted by distance' => $this->getJson('/api/v1/properties?sort=nearest&lat=30.38&lng=30.52&radius_m=20000'),
        'list of brokered ones' => $this->getJson('/api/v1/properties?brokered=true'),
        'detail as a guest' => $this->getJson("/api/v1/properties/{$brokered->id}"),
        'options' => $this->getJson('/api/v1/real-estate/options'),
    ];

    Sanctum::actingAs($this->resident);
    $responses['list signed in'] = $this->getJson('/api/v1/properties');
    $responses['detail signed in'] = $this->getJson("/api/v1/properties/{$brokered->id}");
    $responses['contact'] = $this->postJson("/api/v1/properties/{$brokered->id}/contact");
    $responses['edit refused'] = $this->patchJson("/api/v1/properties/{$brokered->id}", ['title' => 'محاولة']);
    $responses['archive refused'] = $this->postJson("/api/v1/properties/{$brokered->id}/archive");
    $responses['own list'] = $this->getJson('/api/v1/me/properties');

    expect($responses['detail as a guest']->json('data.advertiser'))->toBe([
        'is_platform' => true,
        'name' => 'عاوز إيه',
        'avatar_url' => null,
        'member_since' => null,
        'has_confirmed_phone' => true,
    ])->and($responses['list as a guest']->json('data.0.id'))->toBe($brokered->id)
        ->and($responses['list sorted by distance']->json('data.0.id'))->toBe($brokered->id);

    foreach ($responses as $name => $response) {
        /** @var TestResponse<\Illuminate\Http\JsonResponse> $response */
        $json = (string) $response->getContent();

        foreach ($traces as $trace) {
            expect(str_contains($json, $trace))->toBeFalse("{$name} carries {$trace}");
        }
    }
});

it('shows the brokered property to its own owner without their own details either', function (): void {
    $owner = User::factory()->withPhone('01098765432')->create(['name' => 'صاحب العقار الحقيقي']);
    $brokered = Property::factory()->for($owner)->inStatus(ContentStatus::Paused)->brokered()->create();
    Sanctum::actingAs($owner);

    $json = (string) $this->getJson("/api/v1/properties/{$brokered->id}")->assertOk()->getContent();

    expect($json)->not->toContain('1098765432')
        ->and($json)->not->toContain($owner->id);
});
