<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Media;

use App\Domains\Media\Models\PendingUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class DeleteUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Someone else's or an expired upload answers 404 (PendingUploadPolicy).
        Gate::authorize('delete', $this->upload());

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function upload(): PendingUpload
    {
        $upload = $this->route('upload');

        // The route binds {upload} to a PendingUpload before this request is resolved.
        if (! $upload instanceof PendingUpload) {
            throw new LogicException('The upload route was not bound.');
        }

        return $upload;
    }

    /**
     * No body.
     *
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [];
    }
}
