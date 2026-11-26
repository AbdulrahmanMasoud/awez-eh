<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminPermissions;
use App\Domains\RealEstate\Actions\UpdateRealEstateSettings;
use App\Domains\RealEstate\Settings\RealEstateSettings;
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
 * إعدادات العقارات: the limits on residents' listings (RealEstateSettings), for the roles that hold
 * `View:PropertySettings` (super_admin by default). Saving goes through
 * UpdateRealEstateSettings, which logs what changed.
 *
 * @property-read Schema $form
 */
final class PropertySettings extends Page
{
    private const int MAX_LISTINGS = 1000;

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::System;

    #[\Override]
    protected static ?int $navigationSort = 5;

    #[\Override]
    protected static ?string $slug = 'real-estate-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $admin = Filament::auth()->user();

        return $admin instanceof User && $admin->mayAdminister(AdminPermissions::REAL_ESTATE_SETTINGS);
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.real_estate_settings.label');
    }

    public function getTitle(): string
    {
        return (string) __('admin.real_estate_settings.label');
    }

    public function mount(RealEstateSettings $settings): void
    {
        $this->form->fill($settings->values());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('admin.real_estate_settings.sections.listings'))->columns(2)->schema([
                    $this->number('active_listings_per_account', self::MAX_LISTINGS),
                    $this->number('new_listings_per_day', self::MAX_LISTINGS),
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
                        Action::make('save')->label(__('admin.real_estate_settings.save'))->submit('save')->keyBindings(['mod+s']),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function save(UpdateRealEstateSettings $updateRealEstateSettings): void
    {
        $admin = Filament::auth()->user();
        abort_unless($admin instanceof User && self::canAccess(), 403);

        /** @var array<string, int|string> $state */
        $state = $this->form->getState();

        $settings = $updateRealEstateSettings->handle($admin, array_map(intval(...), $state));
        $this->form->fill($settings->values());

        Notification::make()->success()->title(__('admin.real_estate_settings.saved'))->send();
    }

    private function number(string $name, int $max): TextInput
    {
        return TextInput::make($name)
            ->label(__("admin.real_estate_settings.fields.{$name}"))
            ->integer()
            ->required()
            ->minValue(1)
            ->maxValue($max);
    }
}
