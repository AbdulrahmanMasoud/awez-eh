<?php

declare(strict_types=1);

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Actions\EnsurePlatformAccount;
use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\RealEstate\Actions\RecordPropertyAgreement;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Domains\RealEstate\Settings\RealEstateSettings;
use App\Filament\Pages\ActivityLog;
use App\Filament\Pages\PropertySettings;
use App\Filament\Resources\Properties\Pages\CreateProperty;
use App\Filament\Resources\Properties\Pages\ListProperties;
use App\Filament\Resources\Properties\Pages\ViewProperty;
use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Resources\Properties\RelationManagers\AgreementsRelationManager;
use App\Support\PhoneNumber;
use Brick\Money\Money;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('public');
    Storage::fake('local');
});

/**
 * @return array<string, mixed>
 */
function brokeredForm(District $district, array $overrides = []): array
{
    return [
        'purpose' => 'sale',
        'type' => 'villa',
        'price' => '4500000',
        'title' => 'فيلا للبيع بحديقة في الحي السابع',
        'description' => 'فيلا دورين وحديقة، عقد مسجل والمعاينة مع المنصة.',
        'area_m2' => 450,
        'bedrooms' => 5,
        'bathrooms' => 3,
        'finishing' => 'luxury',
        'furnished' => false,
        'district_id' => $district->id,
        'latitude' => 30.3811,
        'longitude' => 30.5266,
        'agreement_owner_name' => 'الحاج محمود عبد الله',
        'agreement_owner_phone' => '01112345678',
        'agreement_commission_kind' => 'rate',
        'agreement_commission_rate_percent' => '2.5',
        'agreement_signed_on' => '2026-11-26',
        ...$overrides,
    ];
}

it('shows العقارات to the roles that may, and its pages', function (AdminRole $role, int $list, int $create): void {
    actingAsAdmin($role);
    $property = Property::factory()->create();

    $this->get(PropertyResource::getUrl('index'))->assertStatus($list);
    $this->get(PropertyResource::getUrl('view', ['record' => $property]))->assertStatus($list);
    $this->get(PropertyResource::getUrl('create'))->assertStatus($create);
})->with([
    'super_admin' => [AdminRole::SuperAdmin, 200, 200],
    'moderator' => [AdminRole::Moderator, 200, 403],
    'sales' => [AdminRole::Sales, 200, 200],
    'data_entry' => [AdminRole::DataEntry, 403, 403],
]);

it('lists properties with a tab per status and the filters of the design', function (): void {
    actingAsAdmin();
    $pending = Property::factory()->create();
    $published = Property::factory()->published()->create();
    $brokered = Property::factory()->published()->brokered()->create();

    Livewire::test(ListProperties::class)
        ->assertCanSeeTableRecords([$pending, $published, $brokered])
        ->set('activeTab', 'pending_review')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$published])
        ->set('activeTab', 'all')
        ->filterTable('brokered', true)
        ->assertCanSeeTableRecords([$brokered])
        ->assertCanNotSeeTableRecords([$published, $pending]);
});

it('shows a property\'s page with its point, its masked owner and its status history', function (): void {
    actingAsAdmin(AdminRole::Moderator);
    $owner = User::factory()->withPhone('01012345678')->create(['name' => 'منى عادل']);
    $property = Property::factory()->for($owner)->create(['title' => 'شقة للبيع بجوار الجامعة']);

    Livewire::test(ViewProperty::class, ['record' => $property->getRouteKey()])
        ->callAction('approve')
        ->assertHasNoErrors();

    $this->get(PropertyResource::getUrl('view', ['record' => $property]))
        ->assertOk()
        ->assertSee('شقة للبيع بجوار الجامعة')
        ->assertSee('منى عادل')
        ->assertSee('010*****678')
        ->assertDontSee('01012345678')
        ->assertSee((string) $property->location?->latitude)
        ->assertSee('موافقة');
});

it('offers a moderator only the moves the status machine allows now', function (ContentStatus $status, array $visible, array $hidden): void {
    actingAsAdmin(AdminRole::Moderator);
    $property = Property::factory()->inStatus($status)->create();
    $page = Livewire::test(ViewProperty::class, ['record' => $property->getRouteKey()]);

    foreach ($visible as $action) {
        $page->assertActionVisible($action);
    }

    foreach ($hidden as $action) {
        $page->assertActionHidden($action);
    }
})->with([
    'pending review' => [ContentStatus::PendingReview, ['approve', 'reject'], ['takeDown']],
    'published' => [ContentStatus::Published, ['takeDown'], ['approve', 'reject']],
    'paused' => [ContentStatus::Paused, [], ['approve', 'reject', 'takeDown']],
    'draft' => [ContentStatus::Draft, [], ['approve', 'reject', 'takeDown']],
]);

it('takes a published property down with a reason the owner reads', function (): void {
    actingAsAdmin(AdminRole::Moderator);
    $property = Property::factory()->published()->create();

    Livewire::test(ViewProperty::class, ['record' => $property->getRouteKey()])
        ->callAction('takeDown', ['reason' => 'contact_in_text'])
        ->assertHasNoFormErrors();

    expect($property->refresh()->status)->toBe(ContentStatus::Rejected)
        ->and($property->rejection_reason)->toBe('فيه رقم تليفون أو وسيلة تواصل في الوصف.');
});

it('lets sales neither approve nor reject', function (): void {
    actingAsAdmin(AdminRole::Sales);
    $property = Property::factory()->create();

    Livewire::test(ViewProperty::class, ['record' => $property->getRouteKey()])
        ->assertActionHidden('approve')
        ->assertActionHidden('reject');
});

it('shows a brokered owner\'s number to sales and super admins only, logged', function (AdminRole $role, bool $allowed): void {
    $admin = actingAsAdmin($role);
    $property = Property::factory()->published()->brokered()->create();
    PropertyAgreement::factory()->for($property)->create(['owner_phone' => PhoneNumber::from('01112345678')]);

    $page = Livewire::test(ViewProperty::class, ['record' => $property->getRouteKey()]);

    if (! $allowed) {
        $page->assertActionHidden('revealOwnerPhone');

        return;
    }

    $page->callAction('revealOwnerPhone')->assertNotified((string) __('admin.users.actions.revealed_phone', ['phone' => '01112345678']));

    expect(Activity::query()->where('event', 'phone_revealed')->sole()->causer?->is($admin))->toBeTrue();
})->with([
    [AdminRole::SuperAdmin, true],
    [AdminRole::Sales, true],
    [AdminRole::Moderator, false],
]);

it('lets a moderator see a resident owner\'s number, as on the users\' page', function (): void {
    actingAsAdmin(AdminRole::Moderator);
    $property = Property::factory()->for(User::factory()->withPhone('01012345678'))->create();

    Livewire::test(ViewProperty::class, ['record' => $property->getRouteKey()])
        ->callAction('revealOwnerPhone')
        ->assertNotified((string) __('admin.users.actions.revealed_phone', ['phone' => '01012345678']));
});

it('creates a brokered property for an owner\'s account, waiting for review, with its agreement', function (): void {
    $sales = actingAsAdmin(AdminRole::Sales);
    $district = District::factory()->for(City::factory())->create();
    $owner = User::factory()->create();

    Livewire::test(CreateProperty::class)
        ->fillForm(brokeredForm($district, ['owner_id' => $owner->id]))
        ->call('create')
        ->assertHasNoFormErrors();

    $property = Property::query()->sole();
    $agreement = $property->agreements()->sole();

    expect($property->user_id)->toBe($owner->id)
        ->and($property->brokered)->toBeTrue()
        ->and($property->status)->toBe(ContentStatus::PendingReview)
        ->and($property->city_id)->toBe($district->city_id)
        ->and($property->price?->isEqualTo(Money::of(4_500_000, 'EGP')))->toBeTrue()
        ->and($agreement->owner_name)->toBe('الحاج محمود عبد الله')
        ->and($agreement->owner_phone?->e164())->toBe('+201112345678')
        ->and($agreement->commission_rate_basis_points)->toBe(250)
        ->and($agreement->commission_fixed)->toBeNull()
        ->and($agreement->created_by)->toBe($sales->id)
        ->and(Activity::query()->where('log_name', AdminLog::NAME)->whereIn('event', ['brokered_property_created', RecordPropertyAgreement::EVENT])->count())->toBe(2);

    // The log names the property, never the owner's name or number.
    expect(Activity::query()->get()->toJson())->not->toContain('1112345678')->not->toContain('الحاج محمود');
});

it('puts a brokered property of an owner without an account on the platform account', function (): void {
    actingAsAdmin(AdminRole::Sales);
    $district = District::factory()->for(City::factory())->create();

    Livewire::test(CreateProperty::class)
        ->fillForm(brokeredForm($district, ['agreement_commission_kind' => 'fixed', 'agreement_commission_fixed' => '50000']))
        ->call('create')
        ->assertNotified((string) __('errors.platform_account_missing'));

    expect(Property::query()->count())->toBe(0);

    [$platform] = app(EnsurePlatformAccount::class)->handle();

    Livewire::test(CreateProperty::class)
        ->fillForm(brokeredForm($district, ['agreement_commission_kind' => 'fixed', 'agreement_commission_fixed' => '50000']))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Property::query()->sole()->user_id)->toBe($platform->id)
        ->and(PropertyAgreement::query()->sole()->commission_fixed?->isEqualTo(Money::of(50_000, 'EGP')))->toBeTrue();
});

it('checks the brokered property\'s form', function (array $overrides, string $field): void {
    actingAsAdmin(AdminRole::Sales);
    app(EnsurePlatformAccount::class)->handle();
    $district = District::factory()->for(City::factory())->create();

    Livewire::test(CreateProperty::class)
        ->fillForm(brokeredForm($district, $overrides))
        ->call('create')
        ->assertHasFormErrors([$field]);
})->with([
    'a wrong number' => [['agreement_owner_phone' => '123'], 'agreement_owner_phone'],
    'a rent without a period' => [['purpose' => 'rent'], 'rent_period'],
    'an end before the signing' => [['agreement_ends_on' => '2026-11-01'], 'agreement_ends_on'],
    'no point' => [['latitude' => null], 'latitude'],
]);

it('records an agreement on an existing property, which makes it brokered', function (): void {
    $sales = actingAsAdmin(AdminRole::Sales);
    $property = Property::factory()->published()->create();

    Livewire::test(AgreementsRelationManager::class, ['ownerRecord' => $property, 'pageClass' => ViewProperty::class])
        ->callAction(TestAction::make('create')->table(), [
            'agreement_owner_name' => 'منى عادل',
            'agreement_owner_phone' => '01012345678',
            'agreement_commission_kind' => 'rate',
            'agreement_commission_rate_percent' => '1',
            'agreement_signed_on' => '2026-11-27',
        ])
        ->assertHasNoFormErrors();

    expect($property->refresh()->brokered)->toBeTrue()
        ->and($property->agreements()->sole()->commission_rate_basis_points)->toBe(100)
        ->and(Activity::query()->where('event', RecordPropertyAgreement::EVENT)->sole()->causer?->is($sales))->toBeTrue();
});

it('shows agreements to sales and super admins, never edited or deleted', function (AdminRole $role, bool $sees): void {
    actingAsAdmin($role);
    $property = Property::factory()->published()->brokered()->create();
    $agreement = PropertyAgreement::factory()->for($property)->create();

    expect(AgreementsRelationManager::canViewForRecord($property, ViewProperty::class))->toBe($sees);

    if ($sees) {
        Livewire::test(AgreementsRelationManager::class, ['ownerRecord' => $property, 'pageClass' => ViewProperty::class])
            ->assertCanSeeTableRecords([$agreement])
            ->assertActionDoesNotExist(TestAction::make('edit')->table($agreement))
            ->assertActionDoesNotExist(TestAction::make('delete')->table($agreement));
    }
})->with([
    [AdminRole::SuperAdmin, true],
    [AdminRole::Sales, true],
    [AdminRole::Moderator, false],
    [AdminRole::DataEntry, false],
]);

it('opens an agreement\'s document through a signed URL, logged', function (): void {
    // The real local driver in a folder of its own: Storage::fake() would give unsigned URLs.
    $root = storage_path('framework/testing/private-'.Str::uuid());
    config(['filesystems.disks.local.root' => $root]);
    Storage::forgetDisk('local');
    $sales = actingAsAdmin(AdminRole::Sales);
    $property = Property::factory()->published()->brokered()->create();
    $agreement = PropertyAgreement::factory()->for($property)->create();
    $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    $document = $agreement->addMediaFromString($pdf)->usingFileName('agreement.pdf')->toMediaCollection('documents');

    $component = Livewire::test(AgreementsRelationManager::class, ['ownerRecord' => $property, 'pageClass' => ViewProperty::class])
        ->callAction(TestAction::make('openDocument')->table($agreement), ['document' => (string) $document->getKey()])
        ->assertHasNoFormErrors();

    expect(json_encode($component->effects['xjs'] ?? []))->toContain('signature')
        ->and(Activity::query()->where('event', 'viewed_private_media')->sole()->causer?->is($sales))->toBeTrue();

    File::deleteDirectory($root);
});

it('edits the real estate limits on إعدادات العقارات, super admins only, logged', function (): void {
    $admin = actingAsAdmin();
    $this->get(PropertySettings::getUrl())->assertOk();

    Livewire::test(PropertySettings::class)
        ->assertSchemaStateSet(['active_listings_per_account' => 10, 'new_listings_per_day' => 5])
        ->fillForm(['active_listings_per_account' => 12])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(resolve(RealEstateSettings::class)->active_listings_per_account)->toBe(12);

    $entry = Activity::query()->where('event', AdminLog::SETTINGS_UPDATED)->sole();
    expect($entry->causer?->is($admin))->toBeTrue()
        ->and($entry->attribute_changes?->toArray())->toBe(['old' => ['active_listings_per_account' => 10], 'attributes' => ['active_listings_per_account' => 12]]);

    Livewire::test(ActivityLog::class)->assertTableColumnFormattedStateSet('subject_type', 'إعدادات العقارات', $entry);
});

it('keeps إعدادات العقارات from the other roles', function (AdminRole $role): void {
    actingAsAdmin($role);

    $this->get(PropertySettings::getUrl())->assertForbidden();
})->with([AdminRole::Moderator, AdminRole::Sales, AdminRole::DataEntry]);

it('creates the platform account once, with no way to sign in', function (): void {
    $this->artisan('awez:platform-account')->assertSuccessful();
    $this->artisan('awez:platform-account')->assertSuccessful();

    $platform = User::query()->where('email', config('awez.platform_account.email'))->sole();

    expect($platform->name)->toBe('عاوز إيه')
        ->and($platform->password)->toBeNull()
        ->and($platform->phone)->toBeNull()
        ->and($platform->socialAccounts()->count())->toBe(0)
        ->and($platform->isAdmin())->toBeFalse();
});
