<?php

declare(strict_types=1);

namespace App\Domains\Media\Actions;

use App\Domains\Media\Models\PendingUpload;

/**
 * Removes a pending upload and its files (Medialibrary deletes a model's media with it).
 */
final readonly class DeleteUpload
{
    public function handle(PendingUpload $upload): void
    {
        $upload->delete();
    }
}
