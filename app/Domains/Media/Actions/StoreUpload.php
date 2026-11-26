<?php

declare(strict_types=1);

namespace App\Domains\Media\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Media\Models\PendingUpload;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * A resident's photo for a listing not created yet: held for them until it is attached or expires.
 */
final readonly class StoreUpload
{
    public function __construct(private AddImage $addImage, private Config $config) {}

    public function handle(User $user, string $path): PendingUpload
    {
        $upload = DB::transaction(function () use ($user, $path): PendingUpload {
            $upload = new PendingUpload(['expires_at' => Date::now()->addHours($this->config->integer('awez.media.pending_upload_hours'))]);
            $upload->user()->associate($user)->save();

            $this->addImage->handle($upload, PendingUpload::COLLECTION, $path);

            return $upload;
        });

        // Read after the commit, when the conversions were queued (or, on a sync queue, made).
        return $upload->load('media');
    }
}
