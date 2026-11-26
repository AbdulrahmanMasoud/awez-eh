<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Media;

use App\Domains\Media\Actions\DeleteUpload;
use App\Domains\Media\Models\PendingUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Media\DeleteUploadRequest;
use Illuminate\Http\Response;

/**
 * @group Uploads
 */
final class DeleteUploadController extends Controller
{
    /**
     * Delete an upload
     *
     * The resident changed their mind before creating the listing: the photo and its files go.
     *
     * @urlParam upload string required The upload's id. Example: 019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01
     *
     * @response 204 scenario="deleted"
     * @response 404 scenario="not_found" {"message": "لم نجد ما تبحث عنه.", "code": "not_found", "errors": {}}
     */
    public function __invoke(DeleteUploadRequest $request, PendingUpload $upload, DeleteUpload $deleteUpload): Response
    {
        $deleteUpload->handle($upload);

        return response()->noContent();
    }
}
