<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\RealEstate;

use App\Domains\RealEstate\Actions\CreateProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RealEstate\StorePropertyRequest;
use App\Http\Resources\V1\RealEstate\OwnPropertyResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Real estate
 */
final class StorePropertyController extends Controller
{
    /**
     * List a property
     *
     * Creates the resident's property with its photos (their uploads), as a draft, or sent to
     * review with `submit: true`. It is public only once a moderator approves it. Needs a
     * confirmed phone number, within the account's limits (10 open listings, 5 new a day).
     *
     * @response 201 {"data": {"id": "019ac2f1-7a3e-7d10-9b2c-4e5f6a7b8c9d", "purpose": "rent", "type": "apartment", "title": "شقة للإيجار 3 غرف في الحي الثاني", "description": "الشقة في الدور التالت بأسانسير، قريبة من المدارس والمواصلات.", "price": {"amount": "6500.00", "currency": "EGP"}, "rent_period": "monthly", "area_m2": 140, "bedrooms": 3, "bathrooms": 2, "floor": 3, "furnished": false, "finishing": "finished", "district": {"id": "019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01", "name": "الحي الثاني"}, "address_note": "بجوار مسجد الرحمن", "location": {"lat": 30.37051, "lng": 30.50853, "exact": true}, "brokered": false, "views_count": 0, "published_at": null, "photos": [], "advertiser": {"is_platform": false, "name": "أحمد السيد", "avatar_url": null, "member_since": "2026-11-20", "has_confirmed_phone": true}, "status": "pending_review", "rejection_reason": null, "changed_since_review": false, "editable": true, "allowed_actions": ["archive"], "created_at": "2026-11-27T09:00:00Z"}}
     * @response 401 scenario="unauthenticated" {"message": "سجّل الدخول أولًا.", "code": "unauthenticated", "errors": {}}
     * @response 403 scenario="phone_required" {"message": "أكّد رقم موبايلك الأول عشان الناس تقدر توصلك.", "code": "phone_required", "errors": {}}
     * @response 422 scenario="validation_failed" {"message": "بعض البيانات المرسلة غير صحيحة.", "code": "validation_failed", "errors": {"rent_period": ["حقل مدة الإيجار مطلوب في حال ما إذا كان الغرض يساوي rent."]}}
     * @response 422 scenario="too_many_active_listings" {"message": "وصلت لأقصى عدد إعلانات شغالة. أرشف إعلان قديم الأول.", "code": "too_many_active_listings", "errors": {"limit": 10}}
     * @response 422 scenario="too_many_new_listings" {"message": "وصلت لأقصى عدد إعلانات جديدة النهارده. جرّب بكرة.", "code": "too_many_new_listings", "errors": {"limit": 5}}
     * @response 422 scenario="upload_not_available" {"message": "صورة من الصور مش موجودة أو انتهت مدتها. ارفعها تاني.", "code": "upload_not_available", "errors": {}}
     * @response 422 scenario="too_many_files" {"message": "وصلت لأقصى عدد صور هنا.", "code": "too_many_files", "errors": {"limit": 20}}
     */
    public function __invoke(StorePropertyRequest $request, CreateProperty $createProperty): JsonResponse
    {
        return new OwnPropertyResource($createProperty->handle($request->account(), $request->toData()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
