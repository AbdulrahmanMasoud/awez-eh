<?php

declare(strict_types=1);

namespace App\Support\Database;

use App\Support\AdminArea;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records changes made in the admin panel to a model's fillable attributes (who, what, before
 * and after) in the activity log. Changes made elsewhere (seeders, the API, jobs) are not admin
 * actions and are left out. Locations are left out too: a point is not readable in a log.
 *
 * @phpstan-require-extends Model
 */
trait LogsAdminChanges
{
    use LogsActivity {
        shouldLogEvent as private shouldLogActivityEvent;
    }

    public const string ADMIN_LOG = 'admin';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(self::ADMIN_LOG)
            ->logFillable()
            ->logExcept(['center', 'location'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        return AdminArea::isActive() && $this->shouldLogActivityEvent($eventName);
    }
}
