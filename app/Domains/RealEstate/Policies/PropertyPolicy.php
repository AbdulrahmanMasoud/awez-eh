<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminPermissions;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Auth\Access\Response;

/**
 * What a resident may do with a property through the API, and what an admin may do with it in
 * the panel (`mayAdminister` counts only there, DECISIONS 86).
 */
final class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->mayAdminister(AdminPermissions::for('ViewAny', 'Property'));
    }

    /**
     * Published listings are public; owners see their own in any status. Anything else answers
     * as missing: a draft's id says nothing.
     */
    public function view(?User $user, Property $property): Response
    {
        return $property->status->isPublic()
            || $property->isOwnedBy($user)
            || $user?->mayAdminister(AdminPermissions::for('View', 'Property')) === true
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Editing and every owner's move (submit, pause, resume, sold, rented, archive). Someone
     * else's property answers as missing, as with view.
     */
    public function update(User $user, Property $property): Response
    {
        if ($property->isOwnedBy($user)) {
            return Response::allow();
        }

        return $property->status->isPublic() ? Response::deny() : Response::denyAsNotFound();
    }

    public function delete(User $user, Property $property): bool
    {
        return $property->isOwnedBy($user);
    }

    /**
     * Asking for the owner's number: of a published property only.
     */
    public function contact(User $user, Property $property): Response
    {
        return $property->status->isPublic() ? Response::allow() : Response::denyAsNotFound();
    }

    /** A sales admin lists a brokered property for an owner who signed an agreement. */
    public function create(User $user): bool
    {
        return $user->mayAdminister(AdminPermissions::for('Create', 'Property'));
    }

    public function approve(User $user, Property $property): bool
    {
        return $user->mayAdminister(AdminPermissions::for('Approve', 'Property'));
    }

    /** Rejecting a property waiting for review, and taking down a published one. */
    public function reject(User $user, Property $property): bool
    {
        return $user->mayAdminister(AdminPermissions::for('Reject', 'Property'));
    }

    public function markBrokered(User $user, Property $property): bool
    {
        return $user->mayAdminister(AdminPermissions::for('MarkBrokered', 'Property'));
    }

    /**
     * The owner's full number: for a brokered property only sales and super admins see it
     * (the platform's deal); otherwise whoever may reveal any account's number.
     */
    public function revealOwnerPhone(User $user, Property $property): bool
    {
        return $property->brokered
            ? $user->mayAdminister(AdminPermissions::for('RevealOwnerPhone', 'Property'))
            : $user->mayAdminister(AdminPermissions::for('RevealPhone', 'User'));
    }
}
