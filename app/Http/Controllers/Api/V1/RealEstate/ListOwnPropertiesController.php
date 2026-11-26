<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\RealEstate;

use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Actions\ListOwnProperties;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RealEstate\OwnPropertyResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Real estate
 */
final class ListOwnPropertiesController extends Controller
{
    /**
     * My properties
     *
     * The resident's own properties in every status, newest first, 20 a page, each with why it was
     * rejected and what they may do next (`allowed_actions`: `submit`, `pause`, `resume`,
     * `mark_sold`, `mark_rented`, `archive`).
     *
     * @response {"data": [{"id": "019ac2f1-7a3e-7d10-9b2c-4e5f6a7b8c9d", "purpose": "rent", "type": "apartment", "title": "شقة للإيجار 3 غرف في الحي الثاني", "description": "الشقة في الدور التالت بأسانسير، قريبة من المدارس والمواصلات.", "price": {"amount": "6500.00", "currency": "EGP"}, "rent_period": "monthly", "area_m2": 140, "bedrooms": 3, "bathrooms": 2, "floor": 3, "furnished": false, "finishing": "finished", "district": {"id": "019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01", "name": "الحي الثاني"}, "address_note": "بجوار مسجد الرحمن", "location": {"lat": 30.37051, "lng": 30.50853, "exact": true}, "brokered": false, "views_count": 0, "published_at": null, "photos": [], "advertiser": {"is_platform": false, "name": "أحمد السيد", "avatar_url": null, "member_since": "2026-11-20", "has_confirmed_phone": true}, "status": "pending_review", "rejection_reason": null, "changed_since_review": false, "editable": true, "allowed_actions": ["archive"], "created_at": "2026-11-27T09:00:00Z"}], "links": {"first": null, "last": null, "prev": null, "next": null}, "meta": {"path": "http://localhost:8020/api/v1/me/properties", "per_page": 20, "next_cursor": null, "prev_cursor": null}}
     * @response 401 scenario="unauthenticated" {"message": "سجّل الدخول أولًا.", "code": "unauthenticated", "errors": {}}
     */
    public function __invoke(#[CurrentUser] User $user, ListOwnProperties $listOwnProperties): AnonymousResourceCollection
    {
        return OwnPropertyResource::collection($listOwnProperties->handle($user));
    }
}
