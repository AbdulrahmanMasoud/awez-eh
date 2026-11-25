<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Actions\StartPhoneVerification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\StartPhoneVerificationRequest;
use App\Http\Resources\V1\Identity\PhoneChallengeResource;

/**
 * @group Account
 */
final class StartPhoneVerificationController extends Controller
{
    /**
     * Start confirming a mobile number
     *
     * Answers what the app does next: with `method` `code`, ask for a code of `length` digits and
     * send it to `me/phone/confirm`; with `method` `whatsapp` (coming), open `link` and poll `GET me`.
     * A new start replaces an open one. Limits: 3 starts per number in 10 minutes and 10 a day,
     * 5 per account an hour, 20 per address an hour.
     *
     * @response 201 {"data": {"method": "code", "expires_at": "2026-11-25T18:40:00Z", "length": 6}}
     * @response 409 scenario="phone_taken" {"message": "هذا الرقم مؤكَّد على حساب آخر.", "code": "phone_taken", "errors": {}}
     * @response 422 scenario="invalid_phone_number" {"message": "رقم الموبايل غير صحيح. اكتب رقمًا مصريًا مثل 01012345678.", "code": "invalid_phone_number", "errors": {}}
     * @response 429 scenario="too_many_attempts" {"message": "محاولات كثيرة لتأكيد الرقم. حاول مرة أخرى بعد قليل.", "code": "too_many_attempts", "errors": {}}
     */
    public function __invoke(StartPhoneVerificationRequest $request, StartPhoneVerification $startPhoneVerification): PhoneChallengeResource
    {
        return new PhoneChallengeResource($startPhoneVerification->handle($request->account(), $request->phone(), $request->ipAddress()));
    }
}
