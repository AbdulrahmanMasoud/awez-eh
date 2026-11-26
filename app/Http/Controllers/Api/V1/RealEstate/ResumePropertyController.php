<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\RealEstate;

use App\Domains\RealEstate\Actions\ResumeProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RealEstate\PropertyActionRequest;
use App\Http\Resources\V1\RealEstate\OwnPropertyResource;

/**
 * @group Real estate
 */
final class ResumePropertyController extends Controller
{
    /**
     * Resume it
     *
     * A paused property is public again, or goes back to review first if it was edited while paused. The owner's only. When the property's status does not allow it, the answer lists what
     * is allowed (`action_not_allowed`).
     *
     * @urlParam property string required The property's id. Example: 019ac2f1-7a3e-7d10-9b2c-4e5f6a7b8c9d
     *
     * @response {"data": {"id": "019ac2f1-7a3e-7d10-9b2c-4e5f6a7b8c9d", "status": "pending_review", "allowed_actions": ["archive"]}}
     * @response 403 scenario="forbidden" {"message": "غير مسموح لك بهذا الإجراء.", "code": "forbidden", "errors": {}}
     * @response 404 scenario="not_found" {"message": "لم نجد ما تبحث عنه.", "code": "not_found", "errors": {}}
     * @response 409 scenario="action_not_allowed" {"message": "مينفعش تعمل كده دلوقتي. شوف الحاجات المسموحة.", "code": "action_not_allowed", "errors": {"allowed_actions": ["pause", "mark_sold", "archive"]}}
     */
    public function __invoke(PropertyActionRequest $request, ResumeProperty $resumeProperty): OwnPropertyResource
    {
        return new OwnPropertyResource($resumeProperty->handle($request->property(), $request->account()));
    }
}
