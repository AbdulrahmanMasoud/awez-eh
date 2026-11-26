<?php

declare(strict_types=1);

namespace App\Filament\Review;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One kind of moderated content in المراجعة: properties now; craftsmen, places and used items
 * register theirs in steps 2.3 to 2.5 (AdminPanelServiceProvider), without touching the page.
 * Approving and rejecting go through the kind's own domain Actions.
 */
interface ReviewableKind
{
    /** The tab's key, stable in URLs: `properties`, `providers`, ... */
    public function key(): string;

    public function label(): string;

    /** Whether this admin may approve and reject this kind. */
    public function canReview(User $admin): bool;

    /**
     * Everything of this kind waiting for review, oldest first.
     *
     * @return Builder<Model>
     */
    public function pending(): Builder;

    public function title(Model $record): string;

    public function owner(Model $record): ?string;

    /** The language the owner reads the rejection reason in. */
    public function ownerLocale(Model $record): string;

    /** The price as the panel writes money, if the kind has one. */
    public function price(Model $record): ?string;

    public function thumbnail(Model $record): ?string;

    /**
     * Short labels worth a glance: brokered, reported, ...
     *
     * @return list<string>
     */
    public function flags(Model $record): array;

    /** The record's own page in the panel. */
    public function url(Model $record): string;

    public function approve(Model $record, User $admin): void;

    public function reject(Model $record, User $admin, string $reason): void;
}
