<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\RealEstate;

use App\Domains\RealEstate\Actions\ContactPropertyOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RealEstate\ContactPropertyRequest;
use App\Http\Resources\V1\RealEstate\PropertyContactResource;

/**
 * @group Real estate
 */
final class ContactPropertyOwnerController extends Controller
{
    /**
     * The owner's number
     *
     * For a published property the platform does not broker: the owner's number and a WhatsApp
     * link. A brokered property's owner is reached through the platform only: the app opens a chat
     * or books a viewing (`contact_through_platform`). Limited to 30 an hour per account.
     *
     * @urlParam property string required The property's id. Example: 019ac2f1-7a3e-7d10-9b2c-4e5f6a7b8c9d
     *
     * @response {"data": {"phone": "+201012345678", "whatsapp_url": "https://wa.me/201012345678"}}
     * @response 401 scenario="unauthenticated" {"message": "سجّل الدخول أولًا.", "code": "unauthenticated", "errors": {}}
     * @response 404 scenario="not_found" {"message": "لم نجد ما تبحث عنه.", "code": "not_found", "errors": {}}
     * @response 409 scenario="contact_through_platform" {"message": "العقار ده بوساطة المنصة: كلّمنا من الشات أو احجز معاينة.", "code": "contact_through_platform", "errors": {}}
     * @response 409 scenario="contact_unavailable" {"message": "صاحب الإعلان مش متاح على الموبايل دلوقتي.", "code": "contact_unavailable", "errors": {}}
     * @response 429 scenario="too_many_requests" {"message": "طلبات كثيرة جدًا، حاول مرة أخرى بعد قليل.", "code": "too_many_requests", "errors": {}}
     */
    public function __invoke(ContactPropertyRequest $request, ContactPropertyOwner $contactPropertyOwner): PropertyContactResource
    {
        return new PropertyContactResource($contactPropertyOwner->handle($request->property()));
    }
}
