<?php

declare(strict_types=1);

namespace Database\Factories\Concerns;

use App\Domains\Moderation\Enums\ContentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * States for moderated content. Without one, a row is pending review, as new content is.
 *
 * @phpstan-require-extends Factory
 */
trait HasModerationStates
{
    public function inStatus(ContentStatus $status): static
    {
        return $this->state(function () use ($status): array {
            if ($status === ContentStatus::Draft || $status === ContentStatus::PendingReview) {
                return ['status' => $status];
            }

            $reviewedAt = CarbonImmutable::now()->subMinutes($this->faker->numberBetween(60, 90 * 24 * 60))->startOfMinute();

            if ($status === ContentStatus::Rejected) {
                return [
                    'status' => $status,
                    'reviewed_at' => $reviewedAt,
                    'rejection_reason' => $this->faker->randomElement([
                        'الصور غير واضحة، برجاء رفع صور حقيقية.',
                        'السعر المكتوب غير منطقي.',
                        'البيانات ناقصة: العنوان والمساحة.',
                        'الإعلان مكرر.',
                    ]),
                ];
            }

            return ['status' => $status, 'reviewed_at' => $reviewedAt, 'published_at' => $reviewedAt];
        });
    }

    public function published(): static
    {
        return $this->inStatus(ContentStatus::Published);
    }

    public function rejected(): static
    {
        return $this->inStatus(ContentStatus::Rejected);
    }

    public function paused(): static
    {
        return $this->inStatus(ContentStatus::Paused);
    }
}
