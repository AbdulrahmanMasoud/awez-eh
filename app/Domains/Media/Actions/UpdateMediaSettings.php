<?php

declare(strict_types=1);

namespace App\Domains\Media\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\Media\Settings\MediaSettings;
use Illuminate\Support\Facades\DB;

/**
 * A super admin changes the media limits (إعدادات الصور). The values that changed are written in
 * the activity log, before and after; saving the same values writes nothing.
 */
final readonly class UpdateMediaSettings
{
    public function __construct(private MediaSettings $settings) {}

    /**
     * @param  array<string, int>  $values  Property names of MediaSettings and their new values.
     */
    public function handle(User $admin, array $values): MediaSettings
    {
        $before = $this->settings->values();
        $changed = array_filter(
            array_intersect_key($values, $before),
            fn (int $value, string $name): bool => $before[$name] !== $value,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changed === []) {
            return $this->settings;
        }

        DB::transaction(function () use ($admin, $before, $changed): void {
            $this->settings->fill($changed)->save();

            AdminLog::recordSettings(MediaSettings::group(), $admin, array_intersect_key($before, $changed), $changed);
        });

        return $this->settings;
    }
}
