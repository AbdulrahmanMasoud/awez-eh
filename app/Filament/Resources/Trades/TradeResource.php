<?php

declare(strict_types=1);

namespace App\Filament\Resources\Trades;

use App\Domains\Services\Models\Trade;
use App\Filament\Navigation\AdminNavigationGroup;
use App\Filament\Resources\Trades\Pages\CreateTrade;
use App\Filament\Resources\Trades\Pages\EditTrade;
use App\Filament\Resources\Trades\Pages\ListTrades;
use App\Filament\Support\ReferenceDataFields;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * الحرف, in the order the app lists them. Deactivated, never deleted (DECISIONS 42).
 */
final class TradeResource extends Resource
{
    #[\Override]
    protected static ?string $model = Trade::class;

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::System;

    #[\Override]
    protected static ?int $navigationSort = 12;

    #[\Override]
    protected static ?string $recordTitleAttribute = 'name_ar';

    public static function getModelLabel(): string
    {
        return (string) __('admin.trades.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.trades.plural');
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
                ReferenceDataFields::activeColumn(),
            ])
            // Both on the (is_active, sort_order) index.
            ->filters([TernaryFilter::make('is_active')->label(__('admin.fields.is_active'))])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrades::route('/'),
            'create' => CreateTrade::route('/create'),
            'edit' => EditTrade::route('/{record}/edit'),
        ];
    }
}
