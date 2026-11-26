<?php

declare(strict_types=1);

namespace App\Domains\Media\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Media\Models\PendingUpload;
use Illuminate\Auth\Access\Response;

/**
 * A pending upload is its uploader's alone (invariant 1). Someone else's, or an expired one,
 * answers as if it did not exist: its id says nothing.
 */
final class PendingUploadPolicy
{
    public function view(User $user, PendingUpload $upload): Response
    {
        return $this->ownUnexpired($user, $upload);
    }

    public function delete(User $user, PendingUpload $upload): Response
    {
        return $this->ownUnexpired($user, $upload);
    }

    private function ownUnexpired(User $user, PendingUpload $upload): Response
    {
        return $upload->user()->is($user) && ! $upload->hasExpired()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
