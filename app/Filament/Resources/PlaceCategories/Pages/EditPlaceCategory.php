<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlaceCategories\Pages;

use App\Filament\Resources\PlaceCategories\PlaceCategoryResource;
use Filament\Resources\Pages\EditRecord;

final class EditPlaceCategory extends EditRecord
{
    #[\Override]
    protected static string $resource = PlaceCategoryResource::class;
}
