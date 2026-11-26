<?php

declare(strict_types=1);

namespace App\Domains\Media\Contracts;

use Spatie\MediaLibrary\HasMedia;

/**
 * A model that holds photos or documents, with a count limit per collection
 * (InteractsWithMediaCollections implements it).
 */
interface HasMediaCollections extends HasMedia
{
    /**
     * How many files the collection may hold: 1 for a single-file collection, else MediaSettings'.
     */
    public function mediaLimit(string $collection): int;

    /**
     * The model's key, as the media rows store it (Eloquent's, which declares no return type).
     *
     * @return mixed
     */
    public function getKey();

    /**
     * The model's morph-map name, as the media rows store it.
     *
     * @return string
     */
    public function getMorphClass();
}
