<?php

declare(strict_types=1);

namespace App\Domains\Media\Models;

use App\Domains\Media\Support\MediaConversions;
use App\Domains\Media\Support\MediaDisks;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * One stored file: a photo (a clean JPEG on the private disk, its two WebP sizes on the public one)
 * or a private document. Its folder is its own id, so handing it to another model (AttachUploads)
 * moves no file.
 *
 * @property int|null $width
 * @property int|null $height
 */
#[Fillable([
    'model_type', 'model_id', 'uuid', 'collection_name', 'name', 'file_name', 'mime_type', 'disk', 'conversions_disk',
    'size', 'width', 'height', 'manipulations', 'custom_properties', 'generated_conversions', 'responsive_images', 'order_column',
])]
#[UseFactory(MediaFactory::class)]
final class Media extends BaseMedia
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * The package appends the original's URL when a row is serialized: never for us (the
     * original of a photo is not public, and a document has no URL at all).
     *
     * @var list<string>
     */
    #[\Override]
    protected $appends = [];

    /**
     * A private document: nothing of it is public. A photo's original is on the private disk
     * too, but its sizes are public.
     */
    public function isPrivate(): bool
    {
        return ($this->conversions_disk ?: $this->disk) !== MediaDisks::public();
    }

    /**
     * Every size exists: until then the app shows a placeholder of the right shape.
     */
    public function isProcessed(): bool
    {
        return array_all(MediaConversions::ALL, fn (string $conversion): bool => $this->hasGeneratedConversion($conversion));
    }

    /**
     * A conversion's public URL, or null while it is being made.
     */
    public function conversionUrl(string $conversion): ?string
    {
        return $this->hasGeneratedConversion($conversion) ? $this->getUrl($conversion) : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'order_column' => 'integer',
        ];
    }
}
