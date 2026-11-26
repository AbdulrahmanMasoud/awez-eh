<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Settings\MediaSettings;
use App\Domains\RealEstate\Models\Property;
use App\Filament\Support\MediaFields;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use Tests\Support\Images;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    actingAsAdmin(AdminRole::SuperAdmin);
});

it('cleans an admin\'s upload as a resident\'s, and keeps it in the collection', function (): void {
    $property = Property::factory()->create();
    $photo = UploadedFile::fake()->createWithContent('owner-photo.jpg', Images::jpegWithExif());

    Livewire::test(PhotosHarness::class, ['property' => $property])
        ->set('data.photos', [$photo])
        ->call('save')
        ->assertHasNoErrors();

    $media = $property->refresh()->getFirstMedia('photos');
    expect($media)->toBeInstanceOf(Media::class);

    $stored = (string) Storage::disk('local')->get($media->getPathRelativeToRoot());

    expect($media->file_name)->toBe('photo.jpg')
        ->and($stored)->not->toContain(Images::MAKE)
        ->and($media->isProcessed())->toBeTrue();
});

it('limits the field to the collection\'s count and the settings\' size', function (): void {
    MediaSettings::fake(['property_photos' => 7, 'max_file_size_mb' => 4]);
    $property = Property::factory()->create();

    $field = Livewire::test(PhotosHarness::class, ['property' => $property])->instance()->form->getFlatFields(withHidden: true)['photos'];
    assert($field instanceof SpatieMediaLibraryFileUpload);

    expect($field->getMaxFiles())->toBe(7)
        ->and($field->getMaxSize())->toBe(4096)
        ->and($field->isMultiple())->toBeTrue()
        ->and($field->isReorderable())->toBeTrue()
        ->and($field->getAcceptedFileTypes())->toBe(['image/jpeg', 'image/png', 'image/webp']);
});

it('shows a collection\'s small size in tables', function (): void {
    $column = MediaFields::column('photos');

    expect($column->getCollection())->toBe('photos')
        ->and($column->getConversion())->toBe('thumb');
});

/**
 * A form with the field, as a resource's form will hold it (steps 2.2 to 2.5).
 */
final class PhotosHarness extends Component implements HasForms
{
    use InteractsWithForms;

    public Property $property;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([MediaFields::images('photos')])->statePath('data')->model($this->property);
    }

    public function save(): void
    {
        $this->form->getState();
        $this->form->saveRelationships();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
