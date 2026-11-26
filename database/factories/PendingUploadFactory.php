<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Media\Models\PendingUpload;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;

/**
 * A pending upload without its file: tests that need the photo store one through StoreUpload.
 *
 * @extends Factory<PendingUpload>
 */
final class PendingUploadFactory extends Factory
{
    #[\Override]
    protected $model = PendingUpload::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'expires_at' => Date::now()->addHours(Config::integer('awez.media.pending_upload_hours')),
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => Date::now()->subMinute()]);
    }
}
