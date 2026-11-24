<?php

declare(strict_types=1);

namespace App\Domains\Services\Enums;

/**
 * Where a craftsman is in proving who they are, apart from the moderation of the profile.
 */
enum ProviderVerification: string
{
    case Unverified = 'unverified';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
