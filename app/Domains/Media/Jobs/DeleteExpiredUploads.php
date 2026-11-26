<?php

declare(strict_types=1);

namespace App\Domains\Media\Jobs;

use App\Domains\Media\Models\PendingUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hourly: pending uploads past their 24 hours go, with their files. It takes no id because it
 * acts on whatever has expired; running it twice deletes nothing twice (invariant 7).
 */
#[Timeout(600)]
#[Tries(3)]
#[UniqueFor(3600)]
final class DeleteExpiredUploads implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    private const int CHUNK = 100;

    public function __construct()
    {
        $this->onQueue(Config::string('awez.media.queue'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        // Each delete takes its media rows and files with it (Medialibrary).
        PendingUpload::query()->expired()->chunkById(self::CHUNK, function ($uploads): void {
            foreach ($uploads as $upload) {
                $upload->delete();
            }
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Deleting expired uploads failed; the next hourly run tries again.', ['exception' => $exception::class]);
    }
}
