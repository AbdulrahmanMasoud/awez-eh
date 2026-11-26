<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\RealEstate;

use App\Domains\RealEstate\Actions\UpdateProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RealEstate\UpdatePropertyRequest;
use App\Http\Resources\V1\RealEstate\OwnPropertyResource;

/**
 * @group Real estate
 */
final class UpdatePropertyController extends Controller
{
    /**
     * Edit a property
     *
     * Changes only the fields sent. Changing only the price, or removing or reordering photos,
     * keeps a published property public; any other change, or a new photo, sends it back to review
     * and hides it until approved. Sold, rented and archived properties are not edited.
     *
     * @urlParam property string required The property's id. Example: 019ac2f1-7a3e-7d10-9b2c-4e5f6a7b8c9d
     *
     * @response {"data": {"id": "019ac2f1-7a3e-7d10-9b2c-4e5f6a7b8c9d", "purpose": "rent", "type": "apartment", "title": "شقة للإيجار 3 غرف في الحي الثاني", "description": "الشقة في الدور التالت بأسانسير، قريبة من المدارس والمواصلات.", "price": {"amount": "6500.00", "currency": "EGP"}, "rent_period": "monthly", "area_m2": 140, "bedrooms": 3, "bathrooms": 2, "floor": 3, "furnished": false, "finishing": "finished", "district": {"id": "019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01", "name": "الحي الثاني"}, "address_note": "بجوار مسجد الرحمن", "location": {"lat": 30.37051, "lng": 30.50853, "exact": true}, "brokered": false, "views_count": 0, "published_at": null, "photos": [], "advertiser": {"is_platform": false, "name": "أحمد السيد", "avatar_url": null, "member_since": "2026-11-20", "has_confirmed_phone": true}, "status": "pending_review", "rejection_reason": null, "changed_since_review": false, "editable": true, "allowed_actions": ["archive"], "created_at": "2026-11-27T09:00:00Z"}}
     * @response 403 scenario="forbidden" {"message": "غير مسموح لك بهذا الإجراء.", "code": "forbidden", "errors": {}}
     * @response 404 scenario="not_found" {"message": "لم نجد ما تبحث عنه.", "code": "not_found", "errors": {}}
     * @response 409 scenario="not_editable" {"message": "الإعلان ده اتقفل (اتباع أو اتأجر أو اتأرشف) ومينفعش يتعدّل.", "code": "not_editable", "errors": {}}
     * @response 422 scenario="validation_failed" {"message": "بعض البيانات المرسلة غير صحيحة.", "code": "validation_failed", "errors": {"price": ["يجب أن يكون السعر رقمًا عشريًا بـ 0-2 خانات عشرية."]}}
     */
    public function __invoke(UpdatePropertyRequest $request, UpdateProperty $updateProperty): OwnPropertyResource
    {
        return new OwnPropertyResource($updateProperty->handle($request->property(), $request->account(), $request->toData()));
    }
}
