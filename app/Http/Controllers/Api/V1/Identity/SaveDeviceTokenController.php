<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Actions\SaveDeviceToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\SaveDeviceTokenRequest;
use App\Http\Resources\V1\Identity\DeviceTokenResource;

/**
 * @group Account
 */
final class SaveDeviceTokenController extends Controller
{
    /**
     * Save the device's push token
     *
     * One push token per signed-in device: sending a new one replaces it, and signing out removes it.
     *
     * @response {"data": {"platform": "android", "app_version": "1.0.0", "last_used_at": "2026-11-25T18:30:00Z"}}
     * @response 422 scenario="validation_failed" {"message": "بعض البيانات المرسلة غير صحيحة.", "code": "validation_failed", "errors": {"platform": ["قيمة حقل نظام التشغيل غير موجودة في قائمة القيم المسموح بها."]}}
     */
    public function __invoke(SaveDeviceTokenRequest $request, SaveDeviceToken $saveDeviceToken): DeviceTokenResource
    {
        return new DeviceTokenResource($saveDeviceToken->handle(
            $request->account(),
            $request->accessToken(),
            $request->string('token')->toString(),
            $request->platform(),
            $request->filled('app_version') ? $request->string('app_version')->toString() : null,
        ));
    }
}
