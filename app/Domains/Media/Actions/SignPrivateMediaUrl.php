<?php

declare(strict_types=1);

namespace App\Domains\Media\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\Media\Models\Media;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * A private document leaves the storage only as a signed URL that expires in minutes, for an admin
 * the owning model's policy allows (`viewPrivateMedia`), and each one is written in the activity log.
 */
final readonly class SignPrivateMediaUrl
{
    public const string EVENT = 'viewed_private_media';

    public function __construct(private Config $config) {}

    public function handle(User $admin, Media $media): string
    {
        if (! $media->isPrivate()) {
            throw new InvalidArgumentException('Only private media is opened through a signed URL.');
        }

        Gate::forUser($admin)->authorize('viewPrivateMedia', $media->model);

        AdminLog::record(self::EVENT, $media, $admin, after: ['collection' => $media->collection_name]);

        return $media->getTemporaryUrl(Date::now()->addMinutes($this->config->integer('awez.media.signed_url_minutes')));
    }
}
