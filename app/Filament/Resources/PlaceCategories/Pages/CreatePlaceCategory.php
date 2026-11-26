<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlaceCategories\Pages;

use App\Filament\Resources\PlaceCategories\PlaceCategoryResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePlaceCategory extends CreateRecord
{
    #[\Override]
    protected static string $resource = PlaceCategoryResource::class;
}
