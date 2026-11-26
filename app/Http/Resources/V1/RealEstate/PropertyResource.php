<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\RealEstate;

use App\Domains\Identity\Models\User;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Support\MediaConversions;
use App\Domains\RealEstate\Models\Property;
use App\Http\Resources\V1\Media\MediaResource;
use App\Support\Geo\Point;
use Brick\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A property as residents see it. The location is rounded to 3 decimals (about 100 m): the exact
 * point of someone's home is for its owner and admins only (DECISIONS 109). A brokered property's
 * advertiser is the platform: nothing here names or reaches its owner (invariant 2).
 *
 * @property Property $resource
 */
final class PropertyResource extends JsonResource
{
    /** Decimals of a public location: 0.001° is about 111 m of latitude. */
    private const int PUBLIC_PRECISION = 3;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user('sanctum');

        return self::fields($this->resource, $this->resource->isOwnedBy($viewer instanceof User ? $viewer : null));
    }

    /**
     * @return array<string, mixed>
     */
    public static function fields(Property $property, bool $exactLocation): array
    {
        $location = $property->location;
        $price = $property->price;
        $precision = $exactLocation ? null : self::PUBLIC_PRECISION;

        return [
            'id' => $property->id,
            'purpose' => $property->purpose->value,
            'type' => $property->type->value,
            'title' => $property->title,
            'description' => $property->description,
            // Both columns are required: the null checks only satisfy the casts' nullable types.
            'price' => $price instanceof Money ? ['amount' => (string) $price->getAmount(), 'currency' => $price->getCurrency()->getCurrencyCode()] : null,
            'rent_period' => $property->rent_period?->value,
            'area_m2' => $property->area_m2,
            'bedrooms' => $property->bedrooms,
            'bathrooms' => $property->bathrooms,
            'floor' => $property->floor,
            'furnished' => $property->furnished,
            'finishing' => $property->finishing?->value,
            'district' => ['id' => $property->district_id, 'name' => $property->district?->name],
            'address_note' => $property->address_note,
            'location' => $location instanceof Point ? [
                'lat' => $precision === null ? $location->latitude : round($location->latitude, $precision),
                'lng' => $precision === null ? $location->longitude : round($location->longitude, $precision),
                'exact' => $precision === null,
            ] : null,
            'brokered' => $property->brokered,
            'views_count' => $property->views_count,
            'published_at' => $property->published_at?->toIso8601ZuluString(),
            'photos' => self::photos($property),
            'advertiser' => self::advertiser($property),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function photos(Property $property): array
    {
        $photos = [];

        foreach ($property->getMedia(Property::PHOTOS) as $media) {
            if ($media instanceof Media) {
                $photos[] = MediaResource::fields($media);
            }
        }

        return $photos;
    }

    /**
     * @return array<string, mixed>
     */
    private static function advertiser(Property $property): array
    {
        $owner = $property->user;

        if ($property->brokered || ! $owner instanceof User) {
            return [
                'is_platform' => true,
                'name' => (string) __('real_estate.platform_name'),
                'avatar_url' => null,
                'member_since' => null,
                'has_confirmed_phone' => true,
            ];
        }

        $avatar = $owner->getFirstMedia(User::AVATAR);

        return [
            'is_platform' => false,
            'name' => $owner->name,
            'avatar_url' => ($avatar instanceof Media ? $avatar->conversionUrl(MediaConversions::THUMB) : null) ?? $owner->avatar_url,
            'member_since' => $owner->created_at?->toDateString(),
            'has_confirmed_phone' => $owner->phone_verified_at !== null,
        ];
    }
}
