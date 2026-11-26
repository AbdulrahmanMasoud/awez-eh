<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Media;

use App\Domains\Media\Actions\StoreUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Media\StoreUploadRequest;
use App\Http\Resources\V1\Media\PendingUploadResource;

/**
 * @group Uploads
 */
final class StoreUploadController extends Controller
{
    /**
     * Upload a photo
     *
     * For a listing being written: the app uploads each photo first, then sends the uploads' ids
     * when it creates the listing. An upload is kept 24 hours. Its sizes are made in the
     * background: `processing` is true and the URLs null until then; poll `GET /uploads/{id}`.
     * The location, the camera and every other piece of metadata are removed before it is stored.
     * 60 uploads an hour per account.
     *
     * @response 201 {"data": {"id": "019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01", "thumb_url": null, "large_url": null, "width": 2560, "height": 1920, "processing": true, "expires_at": "2026-11-27T10:00:00Z"}}
     * @response 401 scenario="unauthenticated" {"message": "سجّل الدخول أولًا.", "code": "unauthenticated", "errors": {}}
     * @response 422 scenario="validation_failed" {"message": "بعض البيانات المرسلة غير صحيحة.", "code": "validation_failed", "errors": {"file": ["الصورة لازم تكون JPEG أو PNG أو WebP."]}}
     * @response 422 scenario="unreadable_image" {"message": "مش قادرين نقرا الصورة دي. جرّب صورة تانية.", "code": "unreadable_image", "errors": {}}
     * @response 429 scenario="too_many_requests" {"message": "طلبات كثيرة جدًا، حاول مرة أخرى بعد قليل.", "code": "too_many_requests", "errors": {}}
     */
    public function __invoke(StoreUploadRequest $request, StoreUpload $storeUpload): PendingUploadResource
    {
        return new PendingUploadResource($storeUpload->handle($request->account(), $request->uploadedImage()->getRealPath()));
    }
}
