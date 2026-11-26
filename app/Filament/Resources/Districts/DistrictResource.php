<?php

declare(strict_types=1);

namespace App\Filament\Resources\Districts;

use App\Domains\Geography\Models\District;
use App\Filament\Navigation\AdminNavigationGroup;
use App\Filament\Resources\Districts\Pages\CreateDistrict;
use App\Filament\Resources\Districts\Pages\EditDistrict;
use App\Filament\Resources\Districts\Pages\ListDistricts;
use App\Filament\Support\ReferenceDataFields;
use App\Support\Geo\Point;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * المناطق: their own list (by city, in order) and the same under each city.
 */
final class DistrictResource extends Resource
{
    #[\Override]
    protected static ?string $model = District::class;

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::System;

    #[\Override]
    protected static ?int $navigationSort = 11;

    #[\Override]
    protected static ?string $recordTitleAttribute = 'name_ar';

    public static function getModelLabel(): string
    {
        return (string) __('admin.districts.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.districts.plural');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name_ar', 'name_en'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                // Under a city, the district is that city's.
                Select::make('city_id')
                    ->label(__('admin.fields.city'))
                    ->relationship('city', 'name_ar')
                    ->required()
                    ->visible(fn (mixed $livewire): bool => ! $livewire instanceof RelationManager),
                ...ReferenceDataFields::names(),
                // Unique within its city, as the database's index is.
                ReferenceDataFields::slug(unique: false)->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get, mixed $livewire): Unique => $rule->where(
                        'city_id',
                        self::cityOf($livewire, $get),
                    ),
                ),
            ]),
            Section::make(__('admin.fields.center'))->schema([
                ReferenceDataFields::center(required: false, help: (string) __('admin.districts.center_help')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ...ReferenceDataFields::nameColumns(),
                TextColumn::make('city.name_ar')->label(__('admin.fields.city')),
                TextColumn::make('sort_order')->label(__('admin.fields.sort_order')),
            ])
            ->filters([
                SelectFilter::make('city')->label(__('admin.fields.city'))->relationship('city', 'name_ar'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([EditAction::make()]);
    }

    /**
     * The district's city: the owner under a city, else the one picked in the form.
     */
    private static function cityOf(mixed $livewire, Get $get): ?string
    {
        $cityId = $livewire instanceof RelationManager ? $livewire->getOwnerRecord()->getKey() : $get('city_id');

        return is_string($cityId) ? $cityId : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function centerFromCoordinates(array $data): array
    {
        return ReferenceDataFields::centerFromCoordinates($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function coordinatesFromCenter(array $data): array
    {
        $center = $data['center'] ?? null;

        return ReferenceDataFields::coordinatesFromCenter($data, $center instanceof Point ? $center : null);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDistricts::route('/'),
            'create' => CreateDistrict::route('/create'),
            'edit' => EditDistrict::route('/{record}/edit'),
        ];
    }
}
