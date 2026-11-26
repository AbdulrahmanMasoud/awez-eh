<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\RealEstate;

use App\Domains\RealEstate\Actions\ListProperties;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RealEstate\ListPropertiesRequest;
use App\Http\Resources\V1\RealEstate\PropertyResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Real estate
 */
final class ListPropertiesController extends Controller
{
    /**
     * Properties
     *
     * Published properties, 20 a page (cursor pagination: pass `meta.next_cursor` as `cursor`).
     * Locations are rounded to about 100 m. No sign-in needed.
     *
     * @unauthenticated
     *
     * @response {"data": [{"id": "019ac2f1-7a3e-7d10-9b2c-4e5f6a7b8c9d", "purpose": "rent", "type": "apartment", "title": "شقة للإيجار 3 غرف في الحي الثاني", "description": "الشقة في الدور التالت بأسانسير، قريبة من المدارس والمواصلات.", "price": {"amount": "6500.00", "currency": "EGP"}, "rent_period": "monthly", "area_m2": 140, "bedrooms": 3, "bathrooms": 2, "floor": 3, "furnished": false, "finishing": "finished", "district": {"id": "019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01", "name": "الحي الثاني"}, "address_note": "بجوار مسجد الرحمن", "location": {"lat": 30.371, "lng": 30.509, "exact": false}, "brokered": false, "views_count": 42, "published_at": "2026-11-27T09:00:00Z", "photos": [{"id": "019ac2f1-7b00-7c11-8d2e-3f4a5b6c7d8e", "thumb_url": "https://media.awezeh.com/019ac2f1-7b00-7c11-8d2e-3f4a5b6c7d8e/conversions/photo-thumb.webp", "large_url": "https://media.awezeh.com/019ac2f1-7b00-7c11-8d2e-3f4a5b6c7d8e/conversions/photo-large.webp", "width": 2560, "height": 1920, "processing": false}], "advertiser": {"is_platform": false, "name": "أحمد السيد", "avatar_url": null, "member_since": "2026-11-20", "has_confirmed_phone": true}}], "links": {"first": null, "last": null, "prev": null, "next": "http://localhost:8020/api/v1/properties?cursor=eyJpZCI6IjAxOWFjMmYxIiwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ"}, "meta": {"path": "http://localhost:8020/api/v1/properties", "per_page": 20, "next_cursor": "eyJpZCI6IjAxOWFjMmYxIiwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ", "prev_cursor": null}}
     * @response 422 scenario="validation_failed" {"message": "بعض البيانات المرسلة غير صحيحة.", "code": "validation_failed", "errors": {"lat": ["حقل خط العرض مطلوب في حال ما إذا كان الترتيب يساوي nearest."]}}
     */
    public function __invoke(ListPropertiesRequest $request, ListProperties $listProperties): AnonymousResourceCollection
    {
        return PropertyResource::collection($listProperties->handle($request->toData()));
    }
}
