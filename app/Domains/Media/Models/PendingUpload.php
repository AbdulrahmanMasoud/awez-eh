<?php

declare(strict_types=1);

namespace App\Domains\Media\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Media\Concerns\InteractsWithMediaCollections;
use App\Domains\Media\Contracts\HasMediaCollections;
use App\Domains\Media\Policies\PendingUploadPolicy;
use App\Domains\Media\Settings\MediaSettings;
use Carbon\CarbonImmutable;
use Database\Factories\PendingUploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;

/**
 * A photo a resident uploaded while writing a listing, before the listing exists. It is theirs
 * alone, for 24 hours; AttachUploads gives its file to the listing.
 *
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['expires_at'])]
#[UseFactory(PendingUploadFactory::class)]
#[UsePolicy(PendingUploadPolicy::class)]
final class PendingUpload extends Model implements HasMediaCollections
{
    /** @use HasFactory<PendingUploadFactory> */
    use HasFactory;

    use HasUuids;
    use InteractsWithMediaCollections;

    public const string COLLECTION = 'file';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addImageCollection(self::COLLECTION)->singleFile();
    }

    public function file(): ?Media
    {
        $media = $this->getFirstMedia(self::COLLECTION);

        return $media instanceof Media ? $media : null;
    }

    /** From `expires_at` on, as the `expired` scope and AttachUploads count it. */
    public function hasExpired(): bool
    {
        return ! $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function expired(Builder $query): void
    {
        $query->where('expires_at', '<=', Date::now());
    }

    protected function collectionLimits(MediaSettings $settings): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
