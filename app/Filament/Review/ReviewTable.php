<?php

declare(strict_types=1);

namespace App\Filament\Review;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\RejectionReason;
use App\Domains\Moderation\Exceptions\ActionNotAllowed;
use App\Domains\Moderation\Exceptions\ReasonRequired;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The review table المراجعة and محتاج قرارك share, for one kind: thumbnail, title, owner, price,
 * age and flags; approve in one click, reject with a required reason.
 */
final class ReviewTable
{
    private const int MAX_DETAILS = 1000;

    public static function configure(Table $table, ?ReviewableKind $kind, ?int $limit = null): Table
    {
        $query = $kind?->pending();

        return $table
            ->query($query instanceof Builder ? $limit === null ? $query : $query->limit($limit) : (null))
            ->paginated($limit === null)
            ->defaultKeySort(false)
            ->emptyStateHeading(__('admin.review.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->recordUrl(fn (Model $record): ?string => $kind?->url($record))
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->state(fn (Model $record): ?string => $kind?->thumbnail($record))
                    ->square()
                    ->imageSize(48),
                TextColumn::make('review_title')
                    ->label(__('admin.review.columns.title'))
                    ->state(fn (Model $record): string => $kind?->title($record) ?? '')
                    ->description(fn (Model $record): ?string => $kind?->owner($record))
                    ->wrap(),
                TextColumn::make('review_price')
                    ->label(__('admin.review.columns.price'))
                    ->state(fn (Model $record): ?string => $kind?->price($record))
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('admin.review.columns.waiting'))
                    ->since(),
                TextColumn::make('review_flags')
                    ->label(__('admin.review.columns.flags'))
                    ->state(fn (Model $record): array => $kind?->flags($record) ?? [])
                    ->badge()
                    ->color('warning')
                    ->placeholder('—'),
            ])
            ->recordActions([
                self::approve($kind),
                self::reject($kind),
            ]);
    }

    private static function approve(?ReviewableKind $kind): Action
    {
        return Action::make('approve')
            ->label(__('admin.review.approve'))
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->action(function (Model $record) use ($kind): void {
                try {
                    $kind?->approve($record, self::admin());
                } catch (ActionNotAllowed) {
                    Notification::make()->warning()->title(__('admin.review.already_reviewed'))->send();

                    return;
                }

                Notification::make()->success()->title(__('admin.review.approved'))->send();
            });
    }

    private static function reject(?ReviewableKind $kind): Action
    {
        return Action::make('reject')
            ->label(__('admin.review.reject'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->modalDescription(__('admin.review.reject_help'))
            ->schema([
                Select::make('reason')
                    ->label(__('admin.review.reason'))
                    ->options(RejectionReason::options())
                    ->required()
                    ->live(),
                Textarea::make('details')
                    ->label(__('admin.review.details'))
                    ->required(fn (Get $get): bool => $get('reason') === RejectionReason::Other->value)
                    ->maxLength(self::MAX_DETAILS)
                    ->rows(3),
            ])
            ->action(function (Model $record, array $data) use ($kind): void {
                if (! $kind instanceof ReviewableKind) {
                    return;
                }

                $reason = RejectionReason::compose(
                    is_string($data['reason'] ?? null) ? $data['reason'] : null,
                    is_string($data['details'] ?? null) ? $data['details'] : null,
                    $kind->ownerLocale($record),
                );

                try {
                    $kind->reject($record, self::admin(), $reason);
                } catch (ActionNotAllowed|ReasonRequired $exception) {
                    Notification::make()->warning()->title(__($exception->translationKey))->send();

                    return;
                }

                Notification::make()->success()->title(__('admin.review.rejected'))->send();
            });
    }

    private static function admin(): User
    {
        $admin = Filament::auth()->user();

        return $admin instanceof User ? $admin : throw new LogicException('An admin is signed in.');
    }
}
