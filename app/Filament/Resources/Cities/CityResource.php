<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cities;

use App\Domains\Geography\Models\City;
use App\Filament\Navigation\AdminNavigationGroup;
use App\Filament\Resources\Cities\Pages\CreateCity;
use App\Filament\Resources\Cities\Pages\EditCity;
use App\Filament\Resources\Cities\Pages\ListCities;
use App\Filament\Resources\Cities\RelationManagers\DistrictsRelationManager;
use App\Filament\Support\ReferenceDataFields;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * المدن. Deactivated, never deleted: content points at them (DECISIONS 42).
 */
final class CityResource extends Resource
{
    #[\Override]
    protected static ?string $model = City::class;

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::System;

    #[\Override]
    protected static ?int $navigationSort = 10;

    #[\Override]
    protected static ?string $recordTitleAttribute = 'name_ar';

    public static function getModelLabel(): string
    {
        return (string) __('admin.cities.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.cities.plural');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name_ar', 'name_en'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                ...ReferenceDataFields::names(),
                ReferenceDataFields::slug(),
                ReferenceDataFields::active(),
            ]),
            Section::make(__('admin.fields.center'))->schema([
                ReferenceDataFields::center(required: true, help: (string) __('admin.cities.center_help')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ...ReferenceDataFields::nameColumns(),
                TextColumn::make('districts_count')->label(__('admin.fields.districts_count'))->counts('districts'),
                ReferenceDataFields::activeColumn(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [DistrictsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCities::route('/'),
            'create' => CreateCity::route('/create'),
            'edit' => EditCity::route('/{record}/edit'),
        ];
    }
}
