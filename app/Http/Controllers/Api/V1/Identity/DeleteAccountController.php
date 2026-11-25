<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Actions\DeleteAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\DeleteAccountRequest;
use Illuminate\Http\Response;

/**
 * @group Account
 */
final class DeleteAccountController extends Controller
{
    /**
     * Delete the account
     *
     * Signs out of every device and removes the personal data. Listings, requests, favorites and
     * alerts go; conversations, bookings and reviews stay under the name "حساب محذوف". Signing in
     * again with the same Google or Apple account starts a new account.
     *
     * @response 204 scenario="deleted"
     * @response 401 scenario="unauthenticated" {"message": "سجّل الدخول أولًا.", "code": "unauthenticated", "errors": {}}
     * @response 409 scenario="last_super_admin" {"message": "لازم يفضل مدير نظام واحد على الأقل.", "code": "last_super_admin", "errors": {}}
     */
    public function __invoke(DeleteAccountRequest $request, DeleteAccount $deleteAccount): Response
    {
        $deleteAccount->handle($request->account());

        return response()->noContent();
    }
}
