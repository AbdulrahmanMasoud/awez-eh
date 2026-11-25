<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Dashboard\DashboardFigures;
use App\Filament\Dashboard\DashboardPeriod;
use Carbon\CarbonInterface;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Number;

/**
 * The dashboard's stat cards whose data exists now (ADMIN_PANEL.md, step 1.5): the review queue,
 * listings published and new users. Leads and commission join with their steps (4.1, 4.3).
 */
final class OverviewStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    #[\Override]
    protected static ?int $sort = 1;

    #[\Override]
    protected ?string $pollingInterval = null;

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $figures = new DashboardFigures;
        $period = DashboardPeriod::fromFilter($this->pageFilters['period'] ?? null);
        $pending = $figures->pendingReview();
        $published = $figures->published($period);
        $users = $figures->newUsers($period);

        return [
            Stat::make(__('admin.dashboard.pending.label'), Number::format($pending['count']))
                ->description($pending['oldest'] instanceof CarbonInterface
                    ? __('admin.dashboard.pending.oldest', ['age' => $pending['oldest']->settings(['locale' => app()->getLocale()])->diffForHumans(Date::now(), syntax: CarbonInterface::DIFF_ABSOLUTE)])
                    : __('admin.dashboard.pending.none'))
                ->extraAttributes(['class' => 'awez-stat-dark']),
            Stat::make(__('admin.dashboard.published.label'), Number::format($published['total']))
                ->description(__('admin.dashboard.published.split', array_map(Number::format(...), $published))),
            Stat::make(__('admin.dashboard.new_users.label'), Number::format($users['count']))
                ->description(__('admin.dashboard.new_users.confirmed', ['percent' => $users['confirmed_percent']]))
                ->color('primary'),
        ];
    }
}
