<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cities\RelationManagers;

use App\Filament\Resources\Districts\DistrictResource;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A city's districts, in their order, under the city.
 */
final class DistrictsRelationManager extends RelationManager
{
    #[\Override]
    protected static string $relationship = 'districts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string) __('admin.districts.plural');
    }

    public function form(Schema $schema): Schema
    {
        return DistrictResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return DistrictResource::table($table)
            ->headerActions([CreateAction::make()->mutateDataUsing(DistrictResource::centerFromCoordinates(...))])
            ->recordActions([EditAction::make()
                ->mutateRecordDataUsing(DistrictResource::coordinatesFromCenter(...))
                ->mutateDataUsing(DistrictResource::centerFromCoordinates(...))]);
    }
}
