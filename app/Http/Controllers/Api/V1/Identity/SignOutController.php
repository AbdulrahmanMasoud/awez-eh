<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Actions\SignOut;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\SignOutRequest;
use Illuminate\Http\Response;

/**
 * @group Sign-in
 */
final class SignOutController extends Controller
{
    /**
     * Sign out of this device
     *
     * Deletes the token sent, and the device's push token with it.
     *
     * @response 204 scenario="signed out"
     * @response 401 scenario="unauthenticated" {"message": "سجّل الدخول أولًا.", "code": "unauthenticated", "errors": {}}
     */
    public function __invoke(SignOutRequest $request, SignOut $signOut): Response
    {
        $signOut->handle($request->accessToken());

        return response()->noContent();
    }
}
