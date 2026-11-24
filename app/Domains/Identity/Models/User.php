<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Moderation\Models\Report;
use App\Domains\Places\Models\Place;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Domains\Requests\Models\RequestReply;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Services\Models\Provider;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
final class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    // Primary keys are UUID v7: HasUuids generates them time-ordered, so new rows append to the index.
    use HasUuids;
    use Notifiable;

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * The craftsman profile, for an account that has one.
     *
     * @return HasOne<Provider, $this>
     */
    public function provider(): HasOne
    {
        return $this->hasOne(Provider::class);
    }

    /**
     * Places the user claimed.
     *
     * @return HasMany<Place, $this>
     */
    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    /**
     * @return HasMany<UsedItem, $this>
     */
    public function usedItems(): HasMany
    {
        return $this->hasMany(UsedItem::class);
    }

    /**
     * @return HasMany<ServiceRequest, $this>
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /**
     * @return HasMany<RequestReply, $this>
     */
    public function requestReplies(): HasMany
    {
        return $this->hasMany(RequestReply::class);
    }

    /**
     * Reports the user filed.
     *
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Content this admin reviewed, and what they resolved or recorded.
     *
     * @return HasMany<Property, $this>
     */
    public function reviewedProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'reviewed_by');
    }

    /**
     * @return HasMany<Provider, $this>
     */
    public function reviewedProviders(): HasMany
    {
        return $this->hasMany(Provider::class, 'reviewed_by');
    }

    /**
     * @return HasMany<Place, $this>
     */
    public function reviewedPlaces(): HasMany
    {
        return $this->hasMany(Place::class, 'reviewed_by');
    }

    /**
     * @return HasMany<UsedItem, $this>
     */
    public function reviewedUsedItems(): HasMany
    {
        return $this->hasMany(UsedItem::class, 'reviewed_by');
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function resolvedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'resolved_by');
    }

    /**
     * @return HasMany<PropertyAgreement, $this>
     */
    public function createdPropertyAgreements(): HasMany
    {
        return $this->hasMany(PropertyAgreement::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
