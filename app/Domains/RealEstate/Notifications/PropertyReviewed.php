<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Notifications;

use App\Domains\Moderation\Enums\ContentAction;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Notifications\Notification;

/**
 * Tells the owner a moderator approved, rejected or took down their property, with the reason.
 * In the owner's own language (User::preferredLocale). Database only; push comes in step 3.4.
 */
final class PropertyReviewed extends Notification
{
    public function __construct(
        public readonly string $propertyId,
        public readonly ContentAction $outcome,
        public readonly ?string $reason,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(object $notifiable): array
    {
        $title = Property::query()->whereKey($this->propertyId)->value('title');

        return [
            'type' => "property_{$this->outcome->value}",
            'property_id' => $this->propertyId,
            'title' => (string) __("real_estate.notifications.{$this->outcome->value}.title"),
            'body' => (string) __("real_estate.notifications.{$this->outcome->value}.body", ['title' => is_string($title) ? $title : '']),
            'reason' => $this->reason,
        ];
    }
}
