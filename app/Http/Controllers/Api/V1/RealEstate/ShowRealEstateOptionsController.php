<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\RealEstate;

use App\Domains\RealEstate\Actions\ShowRealEstateOptions;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RealEstate\RealEstateOptionsResource;

/**
 * @group Real estate
 */
final class ShowRealEstateOptionsController extends Controller
{
    /**
     * Filter options
     *
     * What the filters offer: purposes, types, finishings and rent periods with their Arabic and
     * English labels, and the districts. Cached; it changes when an admin changes a district.
     *
     * @unauthenticated
     *
     * @response {"data": {"purposes": [{"value": "sale", "label_ar": "للبيع", "label_en": "For sale"}, {"value": "rent", "label_ar": "للإيجار", "label_en": "For rent"}], "types": [{"value": "apartment", "label_ar": "شقة", "label_en": "Apartment"}], "finishings": [{"value": "finished", "label_ar": "متشطب", "label_en": "Finished"}], "rent_periods": [{"value": "monthly", "label_ar": "شهري", "label_en": "Monthly"}], "districts": [{"id": "019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01", "city_id": "019ac0d2-4b1d-7b02-9a11-2c3d4e5f6a7b", "name_ar": "الحي الثاني", "name_en": "Second District"}]}}
     */
    public function __invoke(ShowRealEstateOptions $showRealEstateOptions): RealEstateOptionsResource
    {
        return new RealEstateOptionsResource($showRealEstateOptions->handle());
    }
}
