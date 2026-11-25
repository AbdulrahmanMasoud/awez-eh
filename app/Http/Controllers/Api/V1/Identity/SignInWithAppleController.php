<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Actions\SignIn;
use App\Domains\Identity\Enums\IdentityProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\SignInWithAppleRequest;
use App\Http\Resources\V1\Identity\SignInResource;

/**
 * @group Sign-in
 */
final class SignInWithAppleController extends Controller
{
    /**
     * Sign in with Apple
     *
     * Verifies the identity token Sign in with Apple gave the app, with the SHA-256 of `nonce` in it,
     * and signs this device in, as Google's sign-in does. Send `name` when Apple gave the app one
     * (the first sign-in only).
     *
     * @unauthenticated
     *
     * @response 201 scenario="a new account" {"data": {"token": "1|q8Zt3vN0bYk2cW5rT7uE9iO1pA4sD6fG8hJ0kL2m", "user": {"id": "019abf3c-7d2e-7a41-9c5b-2f8e1d6a4b30", "name": "أحمد السيد", "email": "ahmed@example.com", "phone": null, "phone_verified_at": null, "needs_phone": true, "avatar_url": "https://lh3.googleusercontent.com/a/example", "locale": "ar", "district_id": null, "is_advertiser": false, "is_provider": false, "created_at": "2026-11-25T18:30:00Z"}, "needs_phone": true}}
     * @response 401 scenario="invalid_identity_token" {"message": "تعذّر التحقق من تسجيل الدخول. حاول مرة أخرى.", "code": "invalid_identity_token", "errors": {}}
     * @response 403 scenario="account_suspended" {"message": "هذا الحساب موقوف. تواصل معنا لو تظن أن هذا خطأ.", "code": "account_suspended", "errors": {}}
     * @response 422 scenario="validation_failed" {"message": "بعض البيانات المرسلة غير صحيحة.", "code": "validation_failed", "errors": {"nonce": ["حقل رمز التحقق مطلوب."]}}
     */
    public function __invoke(SignInWithAppleRequest $request, SignIn $signIn): SignInResource
    {
        return new SignInResource($signIn->handle(
            IdentityProvider::Apple,
            $request->string('id_token')->toString(),
            nonce: $request->string('nonce')->toString(),
            name: $request->filled('name') ? $request->string('name')->toString() : null,
            deviceName: $request->string('device_name')->toString(),
        ));
    }
}
