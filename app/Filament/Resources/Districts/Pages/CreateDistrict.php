<?php

declare(strict_types=1);

namespace App\Filament\Resources\Districts\Pages;

use App\Filament\Resources\Districts\DistrictResource;
use App\Filament\Support\MapsCenterCoordinates;
use Filament\Resources\Pages\CreateRecord;

final class CreateDistrict extends CreateRecord
{
    use MapsCenterCoordinates;

    #[\Override]
    protected static string $resource = DistrictResource::class;
}
