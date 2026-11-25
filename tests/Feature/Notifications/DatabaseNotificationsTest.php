<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

final class BookingConfirmedProbe extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return ['title' => 'تم تأكيد الحجز'];
    }
}

it('stores a database notification for a user and reads it back', function (): void {
    $user = User::factory()->create();

    $user->notify(new BookingConfirmedProbe);

    $notification = $user->notifications()->sole();

    expect($notification->data)->toBe(['title' => 'تم تأكيد الحجز'])
        ->and($user->unreadNotifications()->count())->toBe(1)
        ->and(DB::table('notifications')->value('notifiable_type'))->toBe('user')
        ->and(DB::table('notifications')->value('notifiable_id'))->toBe($user->id);
});
