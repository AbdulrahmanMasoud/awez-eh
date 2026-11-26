<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Media\Actions\RemoveAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\DeleteAvatarRequest;
use App\Http\Resources\V1\Identity\UserResource;

/**
 * @group Account
 */
final class DeleteAvatarController extends Controller
{
    /**
     * Remove the picture
     *
     * Deletes the uploaded picture; `avatar_url` is the Google or Apple picture again, or null.
     *
     * @response {"data": {"id": "019abf3c-7d2e-7a41-9c5b-2f8e1d6a4b30", "name": "أحمد السيد", "email": "ahmed@example.com", "phone": "+201012345678", "phone_verified_at": "2026-11-25T18:31:02Z", "needs_phone": false, "avatar_url": null, "locale": "ar", "district_id": null, "is_advertiser": false, "is_provider": false, "created_at": "2026-11-25T18:30:00Z"}}
     */
    public function __invoke(DeleteAvatarRequest $request, RemoveAvatar $removeAvatar): UserResource
    {
        return new UserResource($removeAvatar->handle($request->account()));
    }
}
