<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Actions\ConfirmPhone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\ConfirmPhoneRequest;
use App\Http\Resources\V1\Identity\UserResource;

/**
 * @group Account
 */
final class ConfirmPhoneController extends Controller
{
    /**
     * Confirm the mobile number
     *
     * With the code of the open confirmation. Five tries; `errors.attempts_left` says how many remain.
     *
     * @response {"data": {"id": "019abf3c-7d2e-7a41-9c5b-2f8e1d6a4b30", "name": "أحمد السيد", "email": "ahmed@example.com", "phone": "+201012345678", "phone_verified_at": "2026-11-25T18:31:02Z", "needs_phone": false, "avatar_url": null, "locale": "ar", "district_id": null, "is_advertiser": false, "is_provider": false, "created_at": "2026-11-25T18:30:00Z"}}
     * @response 409 scenario="phone_taken" {"message": "هذا الرقم مؤكَّد على حساب آخر.", "code": "phone_taken", "errors": {}}
     * @response 422 scenario="verification_code_invalid" {"message": "كود التأكيد غير صحيح.", "code": "verification_code_invalid", "errors": {"attempts_left": 4}}
     * @response 422 scenario="verification_code_expired" {"message": "انتهت صلاحية كود التأكيد. اطلب كودًا جديدًا.", "code": "verification_code_expired", "errors": {}}
     */
    public function __invoke(ConfirmPhoneRequest $request, ConfirmPhone $confirmPhone): UserResource
    {
        return new UserResource($confirmPhone->handle($request->account(), $request->string('code')->toString()));
    }
}
