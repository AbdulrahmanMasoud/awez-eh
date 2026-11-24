<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Models\Concerns;

use App\Domains\Moderation\Models\Report;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Content residents can report. The model needs a short name in the morph map.
 *
 * @phpstan-require-extends Model
 */
trait Reportable
{
    /**
     * @return MorphMany<Report, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}
