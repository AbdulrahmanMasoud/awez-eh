<?php

declare(strict_types=1);

namespace App\Domains\Media\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Media\Contracts\HasMediaCollections;
use App\Domains\Media\Exceptions\TooManyFiles;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Models\PendingUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Filesystem;

/**
 * Makes a collection hold exactly the given files, in that order: ids of files it already holds,
 * and ids of the resident's pending uploads to add. A file left out is removed. All or nothing.
 *
 * Removed files are deleted from the disk after the commit, so a rollback never loses one.
 */
final readonly class ArrangeCollection
{
    public function __construct(
        private AttachUploads $attachUploads,
        private Filesystem $filesystem,
    ) {}

    /**
     * @param  list<string>  $ids
     * @return bool Whether new files were added.
     */
    public function handle(HasMediaCollections&Model $model, string $collection, User $user, array $ids): bool
    {
        $ids = array_values(array_unique($ids));

        if (count($ids) > $model->mediaLimit($collection)) {
            throw new TooManyFiles($model->mediaLimit($collection));
        }

        return DB::transaction(function () use ($model, $collection, $user, $ids): bool {
            $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->first();

            $held = Media::query()
                ->where('model_type', $model->getMorphClass())
                ->where('model_id', $model->getKey())
                ->where('collection_name', $collection)
                ->get()
                ->keyBy('id');

            // Eloquent's except() filters by primary key.
            $removed = $held->except($ids);
            $new = array_values(array_diff($ids, $held->keys()->all()));

            // Rows go now, files once the transaction commits.
            Media::query()->whereKey($removed->modelKeys())->delete();
            DB::afterCommit(function () use ($removed): void {
                $removed->each(fn (Media $media) => $this->filesystem->removeAllFiles($media));
            });

            // Each pending upload holds one file: its media row keeps its own id once attached.
            $mediaOfUpload = PendingUpload::query()
                // Anything that is not a UUID is refused by AttachUploads below.
                ->whereKey(array_filter($new, Str::isUuid(...)))
                ->whereBelongsTo($user)
                ->with('media')
                ->get()
                ->mapWithKeys(fn (PendingUpload $upload): array => [$upload->id => $upload->file()?->id])
                ->all();

            $this->attachUploads->handle($model, $collection, $user, $new);

            foreach ($ids as $position => $id) {
                Media::query()
                    ->whereKey($held->has($id) ? $id : $mediaOfUpload[$id] ?? null)
                    ->update(['order_column' => $position + 1]);
            }

            $model->unsetRelation('media');

            return $new !== [];
        });
    }
}
