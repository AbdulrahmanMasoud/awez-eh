<?php

declare(strict_types=1);

namespace App\Filament\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * The dashboard's period filter. Today starts at midnight in Cairo; the week and the month are
 * the last 7 and 30 days, so a period always compares with one of the same length.
 */
enum DashboardPeriod: string
{
    case Today = 'today';
    case Week = 'week';
    case Month = 'month';

    private const int WEEK_DAYS = 7;

    private const int MONTH_DAYS = 30;

    public function label(): string
    {
        return (string) __("admin.dashboard.periods.{$this->value}");
    }

    /**
     * The start of the period, in UTC as everything is stored.
     */
    public function start(CarbonImmutable $now): CarbonImmutable
    {
        return match ($this) {
            self::Today => $now->setTimezone(Config::string('awez.display_timezone'))->startOfDay()->utc(),
            self::Week => $now->subDays(self::WEEK_DAYS),
            self::Month => $now->subDays(self::MONTH_DAYS),
        };
    }

    public static function fromFilter(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Month) : self::Month;
    }
}
