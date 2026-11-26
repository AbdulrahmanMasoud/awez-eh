<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\RealEstate;

use App\Domains\Moderation\Enums\ContentAction;
use App\Domains\Moderation\Enums\ContentActor;
use App\Domains\Moderation\Support\ContentStatusMachine;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A property as its owner sees it: everything residents see, with the exact location, its status,
 * why it was rejected, and what the owner may do next (ContentStatusMachine).
 *
 * @property Property $resource
 */
final class OwnPropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $property = $this->resource;
        $machine = resolve(ContentStatusMachine::class);
        $actions = $machine->allowedActions($property->contentState(), ContentActor::Owner);

        return [
            ...PropertyResource::fields($property, exactLocation: true),
            'status' => $property->status->value,
            'rejection_reason' => $property->rejection_reason,
            'changed_since_review' => $property->changed_since_review,
            'editable' => $machine->isEditable($property->status),
            'allowed_actions' => array_map(fn (ContentAction $action): string => $action->value, $actions),
            'created_at' => $property->created_at?->toIso8601ZuluString(),
        ];
    }
}
