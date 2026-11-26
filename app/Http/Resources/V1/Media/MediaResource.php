<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Media;

use App\Domains\Media\Models\Media;
use App\Domains\Media\Support\MediaConversions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A photo as the app shows it: its two sizes (null while they are being made) and its shape, so
 * the app reserves the space before it loads. The original is never exposed.
 *
 * @property Media $resource
 */
final class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::fields($this->resource);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fields(Media $media): array
    {
        return [
            'id' => $media->id,
            'thumb_url' => $media->conversionUrl(MediaConversions::THUMB),
            'large_url' => $media->conversionUrl(MediaConversions::LARGE),
            'width' => $media->width,
            'height' => $media->height,
            'processing' => ! $media->isProcessed(),
        ];
    }
}
