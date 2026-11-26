<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Support;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\Moderation\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity;

/**
 * One status change of moderated content in the activity log: a moderator's in the `admin` log,
 * as every admin action is, an owner's in the `content` log. Together they are the content's
 * status history on its panel page. The event is the action (`approve`, `pause`, ...) or `edit`.
 */
final class ContentLog
{
    public const string NAME = 'content';

    public const string EDIT = 'edit';

    /** @var list<string> */
    public const array LOGS = [AdminLog::NAME, self::NAME];

    public static function record(string $event, Model $subject, User $by, bool $byModerator, ContentStatus $from, ContentStatus $to, ?string $reason = null): ?Activity
    {
        $before = ['status' => $from->value];
        $after = ['status' => $to->value];

        if ($byModerator) {
            return AdminLog::record($event, $subject, $by, $reason, $before, $after);
        }

        return activity(self::NAME)
            ->performedOn($subject)
            ->causedBy($by)
            ->event($event)
            ->withChanges(['old' => $before, 'attributes' => $after])
            ->log($event);
    }
}
