<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\Media\Actions\SignPrivateMediaUrl;
use App\Domains\Media\Models\Media;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Filament\Actions\OpenPrivateMediaAction;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Images;

beforeEach(function (): void {
    Storage::fake('public');

    // The real local driver in a folder of its own: Storage::fake() would replace the signed URLs
    // this file tests with unsigned ones.
    $this->privateRoot = storage_path('framework/testing/private-'.Str::uuid());
    config(['filesystems.disks.local.root' => $this->privateRoot]);
    Storage::forgetDisk('local');
});

afterEach(function (): void {
    File::deleteDirectory($this->privateRoot);
});

function agreementDocument(): Media
{
    $agreement = PropertyAgreement::factory()->create();
    $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    $media = $agreement->addMedia(Images::file($pdf))->usingFileName('agreement.pdf')->toMediaCollection('documents');
    assert($media instanceof Media);

    return $media;
}

it('keeps agreements\' documents on the private disk, with no sizes and no public file', function (): void {
    $media = agreementDocument();

    expect($media->disk)->toBe('local')
        ->and($media->isPrivate())->toBeTrue()
        ->and($media->generated_conversions)->toBe([])
        ->and(Storage::disk('local')->exists($media->getPathRelativeToRoot()))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('serves no private file without a valid signature', function (): void {
    $media = agreementDocument();

    $this->get('/storage/'.$media->getPathRelativeToRoot())->assertForbidden();
    $this->get('/storage/'.$media->getPathRelativeToRoot().'?expires=9999999999&signature=forged')->assertForbidden();
});

it('signs a URL for five minutes for an admin allowed to see agreements, and logs it', function (): void {
    Date::setTestNow('2026-11-26 10:00:00');
    $media = agreementDocument();
    $admin = actingAsAdmin(AdminRole::SuperAdmin);

    $url = app(SignPrivateMediaUrl::class)->handle($admin, $media);

    $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');

    $entry = Activity::query()->where('log_name', AdminLog::NAME)->sole();
    expect($entry->event)->toBe(SignPrivateMediaUrl::EVENT)
        ->and($entry->causer?->is($admin))->toBeTrue()
        ->and($entry->subject?->is($media))->toBeTrue();

    Date::setTestNow('2026-11-26 10:05:01');
    $this->get($url)->assertForbidden();
});

it('refuses a signed URL to an admin without the permission, and outside the panel', function (): void {
    $media = agreementDocument();

    $moderator = actingAsAdmin(AdminRole::Moderator);
    expect(fn () => app(SignPrivateMediaUrl::class)->handle($moderator, $media))->toThrow(AuthorizationException::class);

    $superAdmin = User::factory()->admin(AdminRole::SuperAdmin)->create();
    Filament::setCurrentPanel(null);
    expect(fn () => app(SignPrivateMediaUrl::class)->handle($superAdmin, $media))->toThrow(AuthorizationException::class)
        ->and(Activity::query()->count())->toBe(0);
});

it('opens a document from the panel in a new tab, logging only the click', function (): void {
    $media = agreementDocument();
    $admin = actingAsAdmin(AdminRole::SuperAdmin);

    $component = Livewire::test(PrivateDocumentHarness::class, ['media' => $media])
        ->assertActionVisible(OpenPrivateMediaAction::NAME);

    expect(Activity::query()->count())->toBe(0);

    $component->callAction(OpenPrivateMediaAction::NAME)->assertHasNoErrors();

    expect(Activity::query()->sole()->causer?->is($admin))->toBeTrue()
        ->and(json_encode($component->effects['xjs'] ?? []))->toContain('signature');
});

it('lets sales open agreements\' documents by default', function (): void {
    $media = agreementDocument();
    $sales = actingAsAdmin(AdminRole::Sales);

    $this->get(app(SignPrivateMediaUrl::class)->handle($sales, $media))->assertOk();

    Livewire::test(PrivateDocumentHarness::class, ['media' => $media])
        ->assertActionVisible(OpenPrivateMediaAction::NAME);
});

it('hides the action from an admin without the permission', function (): void {
    $media = agreementDocument();
    actingAsAdmin(AdminRole::DataEntry);

    Livewire::test(PrivateDocumentHarness::class, ['media' => $media])
        ->assertActionHidden(OpenPrivateMediaAction::NAME);
});

/**
 * The action as a resource will hold it (step 2.2 brings the agreements' screen).
 */
final class PrivateDocumentHarness extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Media $media;

    public function openPrivateMediaAction(): Action
    {
        return OpenPrivateMediaAction::make()->record($this->media);
    }

    public function render(): string
    {
        return '<div>{{ $this->getAction(\'openPrivateMedia\') }}<x-filament-actions::modals /></div>';
    }
}
