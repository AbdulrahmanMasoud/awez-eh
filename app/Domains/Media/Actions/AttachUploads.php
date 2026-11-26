<?php

declare(strict_types=1);

namespace App\Domains\Media\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Media\Contracts\HasMediaCollections;
use App\Domains\Media\Exceptions\TooManyFiles;
use App\Domains\Media\Exceptions\UploadNotAvailable;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Models\PendingUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Gives a resident's pending uploads to a model's collection, in the order given, after what it
 * already holds: all of them or none. Phase 2's create and edit Actions call it.
 *
 * The media rows change owner; their files stay where they are (each media row has its own
 * folder) and keep the sizes already made, so nothing is copied inside the transaction.
 */
final readonly class AttachUploads
{
    /**
     * @param  list<string>  $uploadIds
     */
    public function handle(HasMediaCollections $model, string $collection, User $user, array $uploadIds): void
    {
        $uploadIds = array_values(array_unique($uploadIds));

        if ($uploadIds === []) {
            return;
        }

        $target = $model->getMediaCollection($collection)
            ?? throw new InvalidArgumentException("{$model->getMorphClass()} has no media collection {$collection}.");

        DB::transaction(function () use ($model, $collection, $target, $user, $uploadIds): void {
            // Two attaches to the same model wait for each other, so together they cannot pass its limit.
            if ($model instanceof Model) {
                $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->first();
            }

            $uploads = $this->lockUploads($user, $uploadIds);

            $existing = $this->collectionQuery($model, $collection)->count();

            if ($existing + count($uploadIds) > $model->mediaLimit($collection)) {
                throw new TooManyFiles($model->mediaLimit($collection));
            }

            $position = filter_var($this->collectionQuery($model, $collection)->max('order_column'), FILTER_VALIDATE_INT) ?: 0;

            foreach ($uploadIds as $id) {
                $upload = $uploads[$id];
                $media = $upload->file() ?? throw new UploadNotAvailable;

                // Uploads are photos; a collection on other disks would need its files copied.
                if ($media->disk !== $target->diskName || $media->conversions_disk !== ($target->conversionsDiskName ?: $target->diskName)) {
                    throw new InvalidArgumentException("Uploads cannot be attached to {$collection}: it is on another disk.");
                }

                $media->forceFill([
                    'model_type' => $model->getMorphClass(),
                    'model_id' => $model->getKey(),
                    'collection_name' => $collection,
                    'order_column' => ++$position,
                ])->save();

                $upload->deletePreservingMedia();
            }
        });

        if ($model instanceof Model) {
            $model->unsetRelation('media');
        }
    }

    /**
     * @param  list<string>  $uploadIds
     * @return array<string, PendingUpload>
     */
    private function lockUploads(User $user, array $uploadIds): array
    {
        if (array_filter($uploadIds, Str::isUuid(...)) !== $uploadIds) {
            throw new UploadNotAvailable;
        }

        $uploads = PendingUpload::query()
            ->whereKey($uploadIds)
            ->whereBelongsTo($user)
            ->where('expires_at', '>', Date::now())
            ->lockForUpdate()
            ->with('media')
            ->get()
            ->keyBy('id');

        if ($uploads->count() !== count($uploadIds)) {
            throw new UploadNotAvailable;
        }

        /** @var array<string, PendingUpload> */
        return $uploads->all();
    }

    /**
     * @return Builder<Media>
     */
    private function collectionQuery(HasMediaCollections $model, string $collection): Builder
    {
        return Media::query()
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->where('collection_name', $collection);
    }
}
