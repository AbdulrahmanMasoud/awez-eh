<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminPermissions;
use App\Domains\RealEstate\Models\PropertyAgreement;

/**
 * Agreements are the admin panel's only, on their property's page: listed, opened and recorded by
 * sales and super admins; never edited or deleted (they are history). Their scanned documents
 * open for admins holding `ViewDocuments:PropertyAgreement`.
 */
final class PropertyAgreementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->mayAdminister(AdminPermissions::for('ViewAny', 'PropertyAgreement'));
    }

    public function view(User $user, PropertyAgreement $agreement): bool
    {
        return $user->mayAdminister(AdminPermissions::for('View', 'PropertyAgreement'));
    }

    public function create(User $user): bool
    {
        return $user->mayAdminister(AdminPermissions::for('Create', 'PropertyAgreement'));
    }

    public function update(User $user, PropertyAgreement $agreement): bool
    {
        return false;
    }

    public function delete(User $user, PropertyAgreement $agreement): bool
    {
        return false;
    }

    public function viewPrivateMedia(User $user, PropertyAgreement $agreement): bool
    {
        return $user->mayAdminister(AdminPermissions::AGREEMENT_DOCUMENTS);
    }
}
