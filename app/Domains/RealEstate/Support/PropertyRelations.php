<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Support;

/**
 * What the API reads beside a property to show it, eager-loaded in one place: its photos, its
 * district, and its owner with the owner's picture.
 */
final class PropertyRelations
{
    /** @var list<string> */
    public const array SHOWN = ['media', 'district', 'user.media'];
}
