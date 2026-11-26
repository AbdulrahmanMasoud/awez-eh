<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Media;

use App\Domains\Media\Models\PendingUpload;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Media\PendingUploadResource;
use Illuminate\Support\Facades\Gate;

/**
 * @group Uploads
 */
final class ShowUploadController extends Controller
{
    /**
     * An upload
     *
     * Poll it until `processing` is false and the two URLs are there. Only its uploader sees it;
     * an expired upload, or someone else's, is not found.
     *
     * @urlParam upload string required The upload's id. Example: 019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01
     *
     * @response {"data": {"id": "019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01", "thumb_url": "https://media.awezeh.com/019ac0d2-4b20-71aa-9d3c-0e1f2a3b4c5d/conversions/photo-thumb.webp", "large_url": "https://media.awezeh.com/019ac0d2-4b20-71aa-9d3c-0e1f2a3b4c5d/conversions/photo-large.webp", "width": 2560, "height": 1920, "processing": false, "expires_at": "2026-11-27T10:00:00Z"}}
     * @response 404 scenario="not_found" {"message": "لم نجد ما تبحث عنه.", "code": "not_found", "errors": {}}
     */
    public function __invoke(PendingUpload $upload): PendingUploadResource
    {
        Gate::authorize('view', $upload);

        return new PendingUploadResource($upload->load('media'));
    }
}
