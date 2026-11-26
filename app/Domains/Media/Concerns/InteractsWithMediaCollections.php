<?php

declare(strict_types=1);

namespace App\Domains\Media\Concerns;

use App\Domains\Media\Settings\MediaSettings;
use App\Domains\Media\Support\MediaConversions;
use App\Domains\Media\Support\MediaDisks;
use App\Domains\Media\Support\MediaTypes;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Medialibrary's trait plus what every Awez Eh model shares: the two WebP sizes of public images,
 * collections whose disk comes from configuration, and each collection's count limit.
 */
trait InteractsWithMediaCollections
{
    use InteractsWithMedia;

    public function registerMediaConversions(?Media $media = null): void
    {
        // Only photos have sizes. A private document has none: an admin opens the original
        // through a signed URL.
        if ($media instanceof Media && ($media->conversions_disk !== MediaDisks::public() || ! in_array($media->mime_type, MediaTypes::IMAGES, true))) {
            return;
        }

        foreach (MediaConversions::ALL as $name) {
            $conversion = $this->addMediaConversion($name)->nonOptimized()->queued();

            // Fit::Max never enlarges. The height bound is the original's own maximum, so only
            // the width ever decides.
            $conversion->fit(Fit::Max, MediaConversions::width($name), Config::integer('awez.media.max_long_side'));
            $conversion->format('webp');
            $conversion->quality(Config::integer('awez.media.webp_quality'));
        }
    }

    public function mediaLimit(string $collection): int
    {
        $registered = $this->getMediaCollection($collection)
            ?? throw new InvalidArgumentException("{$this->getMorphClass()} has no media collection {$collection}.");

        if ($registered->singleFile) {
            return 1;
        }

        return $this->collectionLimits(resolve(MediaSettings::class))[$collection]
            ?? throw new InvalidArgumentException("{$this->getMorphClass()} sets no limit for {$collection}.");
    }

    /**
     * The count of each collection that holds more than one file.
     *
     * @return array<string, int>
     */
    abstract protected function collectionLimits(MediaSettings $settings): array;

    /**
     * A collection of photos. Only their two sizes are public; the cleaned original sits on the
     * private disk, so no URL reaches it, not even one guessed from a size's URL.
     */
    protected function addImageCollection(string $name): MediaCollection
    {
        return $this->addMediaCollection($name)
            ->useDisk(MediaDisks::private())
            ->storeConversionsOnDisk(MediaDisks::public())
            ->acceptsMimeTypes(MediaTypes::IMAGES);
    }

    protected function addPrivateDocumentCollection(string $name): MediaCollection
    {
        return $this->addMediaCollection($name)
            ->useDisk(MediaDisks::private())
            ->acceptsMimeTypes(MediaTypes::DOCUMENTS);
    }
}
