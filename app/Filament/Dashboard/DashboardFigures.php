<?php

declare(strict_types=1);

namespace App\Filament\Dashboard;

use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Places\Models\Place;
use App\Domains\RealEstate\Models\Property;
use App\Domains\Services\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * The numbers on the dashboard, read straight from the tables (each count uses the
 * `(status, published_at)` index of moderated content). Widgets whose data does not exist yet are
 * left out (ADMIN_PANEL.md: never fake data).
 */
final class DashboardFigures
{
    /** @var list<class-string<Property|Provider|Place|UsedItem>> */
    private const array MODERATED = [Property::class, Provider::class, Place::class, UsedItem::class];

    private const int PERCENT = 100;

    /**
     * The review queue, whatever the period: everything still waiting, and since when the oldest.
     *
     * @return array{count: int, oldest: ?CarbonImmutable}
     */
    public function pendingReview(): array
    {
        $count = 0;
        $oldest = null;

        foreach (self::MODERATED as $model) {
            $pending = $model::query()->where('status', ContentStatus::PendingReview);
            $count += $pending->clone()->count();
            $first = $pending->min('created_at');

            if (is_string($first)) {
                $created = Date::parse($first);
                $oldest = $oldest === null || $created->lessThan($oldest) ? $created : $oldest;
            }
        }

        return ['count' => $count, 'oldest' => $oldest];
    }

    /**
     * @return array{count: int, confirmed_percent: int}
     */
    public function newUsers(DashboardPeriod $period, ?CarbonImmutable $now = null): array
    {
        $joined = User::query()->where('created_at', '>=', $period->start($now ?? Date::now()));
        $count = $joined->clone()->count();
        $confirmed = $joined->clone()->whereNotNull('phone_verified_at')->count();

        return [
            'count' => $count,
            'confirmed_percent' => $count === 0 ? 0 : intdiv($confirmed * self::PERCENT, $count),
        ];
    }

    /**
     * Listings published in the period, by kind.
     *
     * @return array{properties: int, providers: int, places: int, used_items: int, total: int}
     */
    public function published(DashboardPeriod $period, ?CarbonImmutable $now = null): array
    {
        $start = $period->start($now ?? Date::now());

        $figures = [
            'properties' => $this->publishedSince(Property::class, $start),
            'providers' => $this->publishedSince(Provider::class, $start),
            'places' => $this->publishedSince(Place::class, $start),
            'used_items' => $this->publishedSince(UsedItem::class, $start),
        ];

        return [...$figures, 'total' => array_sum($figures)];
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function publishedSince(string $model, CarbonImmutable $start): int
    {
        /** @var Builder<Model> $query */
        $query = $model::query();

        return $query->where('status', ContentStatus::Published)->where('published_at', '>=', $start)->count();
    }
}
