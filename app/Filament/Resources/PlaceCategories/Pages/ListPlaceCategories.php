<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlaceCategories\Pages;

use App\Filament\Resources\PlaceCategories\PlaceCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPlaceCategories extends ListRecords
{
    #[\Override]
    protected static string $resource = PlaceCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
