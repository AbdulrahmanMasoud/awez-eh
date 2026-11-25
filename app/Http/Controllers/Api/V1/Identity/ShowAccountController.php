<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Identity\UserResource;
use Illuminate\Container\Attributes\CurrentUser;

/**
 * @group Account
 */
final class ShowAccountController extends Controller
{
    /**
     * The signed-in account
     *
     * Always the account of the token sent. `needs_phone` is true until a mobile number is confirmed.
     *
     * @response {"data": {"id": "019abf3c-7d2e-7a41-9c5b-2f8e1d6a4b30", "name": "أحمد السيد", "email": "ahmed@example.com", "phone": "+201012345678", "phone_verified_at": "2026-11-25T18:31:02Z", "needs_phone": false, "avatar_url": null, "locale": "ar", "district_id": null, "is_advertiser": false, "is_provider": false, "created_at": "2026-11-25T18:30:00Z"}}
     * @response 401 scenario="unauthenticated" {"message": "سجّل الدخول أولًا.", "code": "unauthenticated", "errors": {}}
     * @response 403 scenario="account_suspended" {"message": "هذا الحساب موقوف. تواصل معنا لو تظن أن هذا خطأ.", "code": "account_suspended", "errors": {}}
     */
    public function __invoke(#[CurrentUser] User $user): UserResource
    {
        return new UserResource($user);
    }
}
