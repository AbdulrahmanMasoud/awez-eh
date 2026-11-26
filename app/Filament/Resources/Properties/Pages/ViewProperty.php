<?php

declare(strict_types=1);

namespace App\Filament\Resources\Properties\Pages;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentAction;
use App\Domains\Moderation\Enums\ContentActor;
use App\Domains\Moderation\Enums\RejectionReason;
use App\Domains\Moderation\Exceptions\ActionNotAllowed;
use App\Domains\Moderation\Support\ContentStatusMachine;
use App\Domains\RealEstate\Actions\ApproveProperty;
use App\Domains\RealEstate\Actions\RejectProperty;
use App\Domains\RealEstate\Actions\RevealPropertyOwnerPhone;
use App\Domains\RealEstate\Actions\TakeDownProperty;
use App\Domains\RealEstate\Models\Property;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Config;
use LogicException;

/**
 * A property's page, with the moves the status machine allows a moderator now (approve, reject,
 * take down) and "show number" for the roles allowed it.
 */
final class ViewProperty extends ViewRecord
{
    private const int MAX_DETAILS = 1000;

    #[\Override]
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->revealOwnerPhone(),
            $this->approve(),
            $this->reject('reject', ContentAction::Reject),
            $this->reject('takeDown', ContentAction::TakeDown),
        ];
    }

    private function approve(): Action
    {
        return Action::make('approve')
            ->label(__('admin.review.approve'))
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->authorize('approve')
            ->visible(fn (): bool => $this->allows(ContentAction::Approve))
            ->action(function (ApproveProperty $approveProperty): void {
                $this->attempt(fn (): Property => $approveProperty->handle($this->property(), $this->admin()), 'admin.review.approved');
            });
    }

    private function reject(string $name, ContentAction $action): Action
    {
        return Action::make($name)
            ->label(__($action === ContentAction::Reject ? 'admin.review.reject' : 'admin.review.take_down'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->authorize('reject')
            ->visible(fn (): bool => $this->allows($action))
            ->modalDescription(__('admin.review.reject_help'))
            ->schema([
                Select::make('reason')->label(__('admin.review.reason'))->options(RejectionReason::options())->required()->live(),
                Textarea::make('details')->label(__('admin.review.details'))
                    ->required(fn (Get $get): bool => $get('reason') === RejectionReason::Other->value)
                    ->maxLength(self::MAX_DETAILS)
                    ->rows(3),
            ])
            ->action(function (array $data) use ($action): void {
                $reason = RejectionReason::compose(
                    is_string($data['reason'] ?? null) ? $data['reason'] : null,
                    is_string($data['details'] ?? null) ? $data['details'] : null,
                    $this->property()->user?->preferredLocale() ?? Config::string('app.locale'),
                );

                $this->attempt(fn () => $action === ContentAction::Reject
                    ? resolve(RejectProperty::class)->handle($this->property(), $this->admin(), $reason)
                    : resolve(TakeDownProperty::class)->handle($this->property(), $this->admin(), $reason), 'admin.review.rejected');
            });
    }

    /**
     * The owner's full number: an account's for a resident's listing, the agreement's for a
     * brokered one (sales and super admins only). Each look is logged.
     */
    private function revealOwnerPhone(): Action
    {
        return Action::make('revealOwnerPhone')
            ->label(__('admin.users.actions.reveal_phone'))
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->authorize('revealOwnerPhone')
            ->action(function (RevealPropertyOwnerPhone $revealPropertyOwnerPhone): void {
                $phone = $revealPropertyOwnerPhone->handle($this->property(), $this->admin());

                Notification::make()
                    ->info()
                    ->title(__('admin.users.actions.revealed_phone', ['phone' => $phone?->national() ?? '—']))
                    ->persistent()
                    ->send();
            });
    }

    private function allows(ContentAction $action): bool
    {
        return resolve(ContentStatusMachine::class)->allows($this->property()->contentState(), $action, ContentActor::Moderator);
    }

    private function attempt(callable $move, string $done): void
    {
        try {
            $move();
        } catch (ActionNotAllowed) {
            Notification::make()->warning()->title(__('admin.review.already_reviewed'))->send();

            return;
        }

        $this->refreshFormData(['status']);
        Notification::make()->success()->title(__($done))->send();
    }

    private function property(): Property
    {
        $record = $this->getRecord();

        return $record instanceof Property ? $record : throw new LogicException('This page shows properties.');
    }

    private function admin(): User
    {
        $admin = Filament::auth()->user();

        return $admin instanceof User ? $admin : throw new LogicException('An admin is signed in.');
    }
}
