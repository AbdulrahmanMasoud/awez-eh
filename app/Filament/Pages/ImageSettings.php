<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminPermissions;
use App\Domains\Media\Actions\UpdateMediaSettings;
use App\Domains\Media\Settings\MediaSettings;
use App\Filament\Navigation\AdminNavigationGroup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * إعدادات الصور: the limits on photos (MediaSettings), for the roles that hold `View:ImageSettings`
 * (super_admin by default). Saving goes through UpdateMediaSettings, which logs what changed.
 *
 * @property-read Schema $form
 */
final class ImageSettings extends Page
{
    private const int MAX_FILE_SIZE_MB = 50;

    private const int MAX_PER_HOUR = 1000;

    private const int MAX_PER_COLLECTION = 100;

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::System;

    #[\Override]
    protected static ?int $navigationSort = 4;

    #[\Override]
    protected static ?string $slug = 'image-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $admin = Filament::auth()->user();

        return $admin instanceof User && $admin->mayAdminister(AdminPermissions::IMAGE_SETTINGS);
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.image_settings.label');
    }

    public function getTitle(): string
    {
        return (string) __('admin.image_settings.label');
    }

    public function mount(MediaSettings $settings): void
    {
        $this->form->fill($settings->values());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('admin.image_settings.sections.uploads'))->columns(2)->schema([
                    $this->number('max_file_size_mb', self::MAX_FILE_SIZE_MB)->suffix(__('admin.image_settings.megabytes')),
                    $this->number('uploads_per_hour', self::MAX_PER_HOUR),
                ]),
                Section::make(__('admin.image_settings.sections.collections'))->columns(3)->schema([
                    $this->number('property_photos', self::MAX_PER_COLLECTION),
                    $this->number('agreement_documents', self::MAX_PER_COLLECTION),
                    $this->number('provider_work_photos', self::MAX_PER_COLLECTION),
                    $this->number('place_photos', self::MAX_PER_COLLECTION),
                    $this->number('used_item_photos', self::MAX_PER_COLLECTION),
                    $this->number('request_photos', self::MAX_PER_COLLECTION),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('admin.image_settings.save'))->submit('save')->keyBindings(['mod+s']),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function save(UpdateMediaSettings $updateMediaSettings): void
    {
        $admin = Filament::auth()->user();
        abort_unless($admin instanceof User && self::canAccess(), 403);

        /** @var array<string, int|string> $state */
        $state = $this->form->getState();

        $settings = $updateMediaSettings->handle($admin, array_map(intval(...), $state));
        $this->form->fill($settings->values());

        Notification::make()->success()->title(__('admin.image_settings.saved'))->send();
    }

    private function number(string $name, int $max): TextInput
    {
        return TextInput::make($name)
            ->label(__("admin.image_settings.fields.{$name}"))
            ->integer()
            ->required()
            ->minValue(1)
            ->maxValue($max);
    }
}
