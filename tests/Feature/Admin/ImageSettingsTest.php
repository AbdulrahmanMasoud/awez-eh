<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\Media\Settings\MediaSettings;
use App\Filament\Pages\ActivityLog;
use App\Filament\Pages\ImageSettings;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->withoutVite();
});

it('opens إعدادات الصور for super admins only', function (AdminRole $role, int $status): void {
    actingAsAdmin($role);

    $this->get(ImageSettings::getUrl())->assertStatus($status);
})->with([
    [AdminRole::SuperAdmin, 200],
    [AdminRole::Moderator, 403],
    [AdminRole::DataEntry, 403],
    [AdminRole::Sales, 403],
]);

it('shows the current limits and saves new ones, logging only what changed', function (): void {
    $admin = actingAsAdmin(AdminRole::SuperAdmin);

    Livewire::test(ImageSettings::class)
        ->assertSchemaStateSet(['property_photos' => 20, 'max_file_size_mb' => 10, 'uploads_per_hour' => 60], 'form')
        ->fillForm(['property_photos' => 25, 'uploads_per_hour' => 40])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $settings = app(MediaSettings::class)->refresh();
    $entry = Activity::query()->where('log_name', AdminLog::NAME)->sole();

    expect($settings->property_photos)->toBe(25)
        ->and($settings->uploads_per_hour)->toBe(40)
        ->and($settings->max_file_size_mb)->toBe(10)
        ->and($entry->event)->toBe(AdminLog::SETTINGS_UPDATED)
        ->and($entry->causer?->is($admin))->toBeTrue()
        ->and($entry->properties?->get('settings'))->toBe('media')
        ->and($entry->attribute_changes?->toArray())->toEqual([
            'old' => ['uploads_per_hour' => 60, 'property_photos' => 20],
            'attributes' => ['uploads_per_hour' => 40, 'property_photos' => 25],
        ]);

    // The activity log names the settings group.
    Livewire::test(ActivityLog::class)
        ->assertCanSeeTableRecords([$entry])
        ->assertTableColumnFormattedStateSet('subject_type', 'إعدادات الصور', $entry)
        ->assertSee('تعديل إعدادات');
});

it('writes nothing when nothing changed', function (): void {
    actingAsAdmin(AdminRole::SuperAdmin);

    Livewire::test(ImageSettings::class)->call('save')->assertHasNoFormErrors();

    expect(Activity::query()->count())->toBe(0);
});

it('refuses limits that make no sense', function (string $field, mixed $value): void {
    actingAsAdmin(AdminRole::SuperAdmin);

    Livewire::test(ImageSettings::class)
        ->fillForm([$field => $value])
        ->call('save')
        ->assertHasFormErrors([$field]);

    expect(Activity::query()->count())->toBe(0);
})->with([
    ['property_photos', 0],
    ['max_file_size_mb', 500],
    ['uploads_per_hour', 'many'],
    ['request_photos', null],
]);

it('applies a new limit to the next upload', function (): void {
    MediaSettings::fake(['max_file_size_mb' => 3]);

    expect(app(MediaSettings::class)->maxFileSizeKilobytes())->toBe(3072);
});
