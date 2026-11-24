<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ReportReason;
use App\Domains\Moderation\Enums\ReportStatus;
use App\Domains\Moderation\Models\Report;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
final class ReportFactory extends Factory
{
    #[\Override]
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reportable_type' => new Property()->getMorphClass(),
            'reportable_id' => Property::factory(),
            'reason' => fake()->randomElement(ReportReason::cases()),
            'note' => fake()->optional(0.5)->randomElement([
                'الرقم مش بيرد والإعلان شكله وهمي.',
                'السعر مختلف عن اللي اتقال في التليفون.',
                'نفس الإعلان متكرر أكتر من مرة.',
            ]),
            'status' => ReportStatus::Open,
            'resolved_by' => null,
            'resolved_at' => null,
        ];
    }
}
