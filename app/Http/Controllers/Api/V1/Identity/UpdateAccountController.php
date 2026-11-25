<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Actions\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\UpdateProfileRequest;
use App\Http\Resources\V1\Identity\UserResource;

/**
 * @group Account
 */
final class UpdateAccountController extends Controller
{
    /**
     * Change the profile
     *
     * Changes only the fields sent: the name, the language of notifications, and the district.
     *
     * @response {"data": {"id": "019abf3c-7d2e-7a41-9c5b-2f8e1d6a4b30", "name": "أحمد السيد", "email": "ahmed@example.com", "phone": "+201012345678", "phone_verified_at": "2026-11-25T18:31:02Z", "needs_phone": false, "avatar_url": null, "locale": "ar", "district_id": null, "is_advertiser": false, "is_provider": false, "created_at": "2026-11-25T18:30:00Z"}}
     * @response 401 scenario="unauthenticated" {"message": "سجّل الدخول أولًا.", "code": "unauthenticated", "errors": {}}
     * @response 422 scenario="validation_failed" {"message": "بعض البيانات المرسلة غير صحيحة.", "code": "validation_failed", "errors": {"locale": ["قيمة حقل اللغة غير موجودة في قائمة القيم المسموح بها."]}}
     */
    public function __invoke(UpdateProfileRequest $request, UpdateProfile $updateProfile): UserResource
    {
        return new UserResource($updateProfile->handle($request->account(), $request->toData()));
    }
}
