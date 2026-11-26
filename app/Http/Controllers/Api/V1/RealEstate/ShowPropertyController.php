<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\RealEstate;

use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Actions\ViewProperty;
use App\Domains\RealEstate\Models\Property;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RealEstate\PropertyResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Real estate
 */
final class ShowPropertyController extends Controller
{
    /**
     * A property
     *
     * A published property, or one of the signed-in resident's own in any status (with its exact
     * location). Anything else is not found. Opening it counts a view, once a day per account or
     * device, never the owner's own.
     *
     * @unauthenticated
     *
     * @urlParam property string required The property's id. Example: 019ac2f1-7a3e-7d10-9b2c-4e5f6a7b8c9d
     *
     * @response {"data": {"id": "019ac2f1-7a3e-7d10-9b2c-4e5f6a7b8c9d", "purpose": "rent", "type": "apartment", "title": "شقة للإيجار 3 غرف في الحي الثاني", "description": "الشقة في الدور التالت بأسانسير، قريبة من المدارس والمواصلات.", "price": {"amount": "6500.00", "currency": "EGP"}, "rent_period": "monthly", "area_m2": 140, "bedrooms": 3, "bathrooms": 2, "floor": 3, "furnished": false, "finishing": "finished", "district": {"id": "019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01", "name": "الحي الثاني"}, "address_note": "بجوار مسجد الرحمن", "location": {"lat": 30.371, "lng": 30.509, "exact": false}, "brokered": false, "views_count": 42, "published_at": "2026-11-27T09:00:00Z", "photos": [{"id": "019ac2f1-7b00-7c11-8d2e-3f4a5b6c7d8e", "thumb_url": "https://media.awezeh.com/019ac2f1-7b00-7c11-8d2e-3f4a5b6c7d8e/conversions/photo-thumb.webp", "large_url": "https://media.awezeh.com/019ac2f1-7b00-7c11-8d2e-3f4a5b6c7d8e/conversions/photo-large.webp", "width": 2560, "height": 1920, "processing": false}], "advertiser": {"is_platform": false, "name": "أحمد السيد", "avatar_url": null, "member_since": "2026-11-20", "has_confirmed_phone": true}}}
     * @response 404 scenario="not_found" {"message": "لم نجد ما تبحث عنه.", "code": "not_found", "errors": {}}
     */
    public function __invoke(Request $request, Property $property, ViewProperty $viewProperty): PropertyResource
    {
        $viewer = $request->user('sanctum');
        $viewer = $viewer instanceof User ? $viewer : null;
        Gate::forUser($viewer)->authorize('view', $property);

        return new PropertyResource($viewProperty->handle($property, $viewer, (string) $request->ip()));
    }
}
