<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cities\Pages;

use App\Filament\Resources\Cities\CityResource;
use App\Filament\Support\MapsCenterCoordinates;
use Filament\Resources\Pages\CreateRecord;

final class CreateCity extends CreateRecord
{
    use MapsCenterCoordinates;

    #[\Override]
    protected static string $resource = CityResource::class;
}
