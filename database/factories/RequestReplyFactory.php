<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Requests\Models\RequestReply;
use App\Domains\Requests\Models\ServiceRequest;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestReply>
 */
final class RequestReplyFactory extends Factory
{
    #[\Override]
    protected $model = RequestReply::class;

    public function definition(): array
    {
        return [
            'request_id' => ServiceRequest::factory(),
            'user_id' => User::factory(),
            'body' => fake()->randomElement([
                'عندي اللي حضرتك محتاجه، كلمني على الشات.',
                'أقدر أجيلك خلال ساعة.',
                'متاح، والمعاينة مجانًا.',
                'عندي حاجة قريبة من طلبك وبسعر كويس.',
            ]),
            'offered_price' => fake()->boolean(50) ? Money::of(fake()->numberBetween(1, 50) * 100, 'EGP') : null,
        ];
    }
}
