<?php

declare(strict_types=1);

namespace App\Filament\Resources\Trades\Pages;

use App\Filament\Resources\Trades\TradeResource;
use Filament\Resources\Pages\EditRecord;

final class EditTrade extends EditRecord
{
    #[\Override]
    protected static string $resource = TradeResource::class;
}
