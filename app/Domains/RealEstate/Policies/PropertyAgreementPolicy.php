<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminPermissions;
use App\Domains\RealEstate\Models\PropertyAgreement;

/**
 * Agreements are the admin panel's only (step 2.2 brings their screen). Their scanned documents
 * open for admins holding `ViewDocuments:PropertyAgreement`, inside the panel.
 */
final class PropertyAgreementPolicy
{
    public function viewPrivateMedia(User $user, PropertyAgreement $agreement): bool
    {
        return $user->mayAdminister(AdminPermissions::AGREEMENT_DOCUMENTS);
    }
}
