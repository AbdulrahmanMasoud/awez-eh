<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Media\Actions\SetAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\UpdateAvatarRequest;
use App\Http\Resources\V1\Identity\UserResource;

/**
 * @group Account
 */
final class UpdateAvatarController extends Controller
{
    /**
     * Change the picture
     *
     * Replaces the account's picture (multipart `file`). Its small size is made in the background:
     * until then `avatar_url` stays the Google or Apple picture. Counted with the uploads (60 an hour).
     *
     * @response {"data": {"id": "019abf3c-7d2e-7a41-9c5b-2f8e1d6a4b30", "name": "أحمد السيد", "email": "ahmed@example.com", "phone": "+201012345678", "phone_verified_at": "2026-11-25T18:31:02Z", "needs_phone": false, "avatar_url": "https://media.awezeh.com/019ac0d2-4b20-71aa-9d3c-0e1f2a3b4c5d/conversions/photo-thumb.webp", "locale": "ar", "district_id": null, "is_advertiser": false, "is_provider": false, "created_at": "2026-11-25T18:30:00Z"}}
     * @response 422 scenario="validation_failed" {"message": "بعض البيانات المرسلة غير صحيحة.", "code": "validation_failed", "errors": {"file": ["الصورة لازم تكون JPEG أو PNG أو WebP."]}}
     * @response 429 scenario="too_many_requests" {"message": "طلبات كثيرة جدًا، حاول مرة أخرى بعد قليل.", "code": "too_many_requests", "errors": {}}
     */
    public function __invoke(UpdateAvatarRequest $request, SetAvatar $setAvatar): UserResource
    {
        return new UserResource($setAvatar->handle($request->account(), $request->uploadedImage()->getRealPath()));
    }
}
