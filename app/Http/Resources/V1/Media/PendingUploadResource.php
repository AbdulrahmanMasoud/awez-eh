<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Media;

use App\Domains\Media\Models\PendingUpload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An upload has the photo's shape (MediaResource), with the upload's own id: the id the app
 * polls, deletes, and sends when it creates the listing.
 *
 * @property PendingUpload $resource
 */
final class PendingUploadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $media = $this->resource->file();

        return [
            ...($media === null ? [] : MediaResource::fields($media)),
            'id' => $this->resource->id,
            'expires_at' => $this->resource->expires_at->toIso8601ZuluString(),
        ];
    }
}
