<?php

declare(strict_types=1);

namespace App\Filament\Resources\Trades\Pages;

use App\Filament\Resources\Trades\TradeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListTrades extends ListRecords
{
    #[\Override]
    protected static string $resource = TradeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
