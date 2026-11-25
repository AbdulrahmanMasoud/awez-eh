<?php

declare(strict_types=1);

namespace App\Filament\Resources\Districts\Pages;

use App\Filament\Resources\Districts\DistrictResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListDistricts extends ListRecords
{
    #[\Override]
    protected static string $resource = DistrictResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
