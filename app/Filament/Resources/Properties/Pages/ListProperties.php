<?php

declare(strict_types=1);

namespace App\Filament\Resources\Properties\Pages;

use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\RealEstate\Models\Property;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every property, with a tab per status and its count.
 */
final class ListProperties extends ListRecords
{
    #[\Override]
    protected static string $resource = PropertyResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Property::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $tabs = ['all' => Tab::make(__('admin.properties.all'))];

        foreach (ContentStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make(PropertyResource::statusLabel($status))
                ->badge(is_numeric($counts[$status->value] ?? null) ? (int) $counts[$status->value] : 0)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status));
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.properties.create_brokered')),
        ];
    }
}
