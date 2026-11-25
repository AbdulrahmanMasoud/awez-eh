<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlaceCategories;

use App\Domains\Places\Models\PlaceCategory;
use App\Filament\Navigation\AdminNavigationGroup;
use App\Filament\Resources\PlaceCategories\Pages\CreatePlaceCategory;
use App\Filament\Resources\PlaceCategories\Pages\EditPlaceCategory;
use App\Filament\Resources\PlaceCategories\Pages\ListPlaceCategories;
use App\Filament\Support\ReferenceDataFields;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * أقسام الأماكن: top-level categories and their subcategories, each in its order.
 */
final class PlaceCategoryResource extends Resource
{
    #[\Override]
    protected static ?string $model = PlaceCategory::class;

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::System;

    #[\Override]
    protected static ?int $navigationSort = 13;

    #[\Override]
    protected static ?string $recordTitleAttribute = 'name_ar';

    public static function getModelLabel(): string
    {
        return (string) __('admin.place_categories.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.place_categories.plural');
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
                // One level: a subcategory's parent is a top-level category, never itself.
                Select::make('parent_id')
                    ->label(__('admin.fields.parent'))
                    ->relationship(
                        'parent',
                        'name_ar',
                        modifyQueryUsing: fn (Builder $query, ?PlaceCategory $record): Builder => $query
                            ->whereNull('parent_id')
                            ->when($record, fn (Builder $query, PlaceCategory $record): Builder => $query->whereKeyNot($record->getKey())),
                    )
                    ->placeholder(__('admin.place_categories.top_level')),
                ReferenceDataFields::icon(),
                ReferenceDataFields::active(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ReferenceDataFields::iconColumn(),
                ...ReferenceDataFields::nameColumns(),
                TextColumn::make('parent.name_ar')->label(__('admin.fields.parent'))->placeholder(__('admin.place_categories.top_level')),
                ReferenceDataFields::activeColumn(),
            ])
            // On the (parent_id, sort_order) index.
            ->filters([SelectFilter::make('parent')->label(__('admin.fields.parent'))->relationship('parent', 'name_ar', fn (Builder $query): Builder => $query->whereNull('parent_id'))])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlaceCategories::route('/'),
            'create' => CreatePlaceCategory::route('/create'),
            'edit' => EditPlaceCategory::route('/{record}/edit'),
        ];
    }
}
