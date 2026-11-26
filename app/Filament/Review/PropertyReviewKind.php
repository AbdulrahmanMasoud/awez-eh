<?php

declare(strict_types=1);

namespace App\Filament\Review;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminPermissions;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Support\MediaConversions;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\RealEstate\Actions\ApproveProperty;
use App\Domains\RealEstate\Actions\RejectProperty;
use App\Domains\RealEstate\Models\Property;
use App\Filament\Resources\Properties\PropertyResource;
use App\Support\AdminNumbers;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use LogicException;

/**
 * Properties in المراجعة.
 */
final readonly class PropertyReviewKind implements ReviewableKind
{
    public function key(): string
    {
        return 'properties';
    }

    public function label(): string
    {
        return (string) __('admin.properties.plural');
    }

    public function canReview(User $admin): bool
    {
        return $admin->mayAdminister(AdminPermissions::for('Approve', 'Property'))
            && $admin->mayAdminister(AdminPermissions::for('Reject', 'Property'));
    }

    /**
     * @return Builder<Model>
     */
    public function pending(): Builder
    {
        /** @var Builder<Model> */
        return Property::query()
            ->where('status', ContentStatus::PendingReview)
            ->with(['media', 'user'])
            ->withCount(['reports'])
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function title(Model $record): string
    {
        return $this->property($record)->title;
    }

    public function owner(Model $record): ?string
    {
        return $this->property($record)->user?->name;
    }

    public function ownerLocale(Model $record): string
    {
        return $this->property($record)->user?->preferredLocale() ?? Config::string('app.locale');
    }

    public function price(Model $record): ?string
    {
        $price = $this->property($record)->price;

        return $price instanceof Money ? $price->formatToLocale(AdminNumbers::localeFor(app()->getLocale())) : null;
    }

    public function thumbnail(Model $record): ?string
    {
        $photo = $this->property($record)->getFirstMedia(Property::PHOTOS);

        return $photo instanceof Media ? $photo->conversionUrl(MediaConversions::THUMB) : null;
    }

    public function flags(Model $record): array
    {
        $property = $this->property($record);
        $reports = $property->getAttribute('reports_count');

        return array_values(array_filter([
            $property->brokered ? (string) __('admin.review.flags.brokered') : null,
            is_int($reports) && $reports > 0 ? (string) __('admin.review.flags.reported') : null,
            $property->published_at !== null ? (string) __('admin.review.flags.edited') : null,
        ]));
    }

    public function url(Model $record): string
    {
        return PropertyResource::getUrl('view', ['record' => $record]);
    }

    public function approve(Model $record, User $admin): void
    {
        resolve(ApproveProperty::class)->handle($this->property($record), $admin);
    }

    public function reject(Model $record, User $admin, string $reason): void
    {
        resolve(RejectProperty::class)->handle($this->property($record), $admin, $reason);
    }

    private function property(Model $record): Property
    {
        return $record instanceof Property ? $record : throw new LogicException('This kind reviews properties.');
    }
}
