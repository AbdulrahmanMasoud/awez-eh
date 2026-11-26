<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Identity\Models\User;
use App\Filament\Dashboard\DashboardFigures;
use App\Filament\Dashboard\DashboardPeriod;
use Filament\Facades\Filament;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * الرئيسية: a greeting with the admin's first name, today's date in Cairo and what is waiting,
 * then the period filter (default: the month) that drives the widgets.
 */
final class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    private const int NOON = 12;

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.dashboard.title');
    }

    public function getTitle(): string
    {
        return (string) __('admin.dashboard.title');
    }

    public function getHeading(): string
    {
        $admin = Filament::auth()->user();
        $firstName = $admin instanceof User ? Str::before(trim($admin->name), ' ') : '';
        $hour = Date::now()->setTimezone(Config::string('awez.display_timezone'))->hour;

        return (string) __($hour < self::NOON ? 'admin.dashboard.morning' : 'admin.dashboard.evening', ['name' => $firstName]);
    }

    public function getSubheading(): string
    {
        $today = Date::now()->setTimezone(Config::string('awez.display_timezone'))->settings(['locale' => app()->getLocale()]);
        $waiting = new DashboardFigures()->pendingReview()['count'];

        return trans_choice('admin.dashboard.waiting', $waiting, ['date' => $today->translatedFormat('l j F'), 'count' => $waiting]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            ToggleButtons::make('period')
                ->hiddenLabel()
                ->options(array_combine(
                    array_column(DashboardPeriod::cases(), 'value'),
                    array_map(fn (DashboardPeriod $period): string => $period->label(), DashboardPeriod::cases()),
                ))
                ->default(DashboardPeriod::Month->value)
                ->inline()
                ->grouped(),
        ]);
    }
}
