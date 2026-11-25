<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Domains\Identity\Actions\ReactivateUser;
use App\Domains\Identity\Actions\RecordPhoneReveal;
use App\Domains\Identity\Actions\SuspendUser;
use App\Domains\Identity\Actions\UpdateUserRoles;
use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Exceptions\LastSuperAdmin;
use App\Domains\Identity\Models\User;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use LogicException;

/**
 * One account: its profile with the number masked, how it signs in, its devices and how much it
 * has posted, and what admins may do to it.
 */
final class ViewUser extends ViewRecord
{
    private const int MAX_REASON = 1000;

    #[\Override]
    protected static string $resource = UserResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->user()->loadCount(['properties', 'usedItems', 'places', 'serviceRequests', 'requestReplies', 'residentBookings', 'reviews'])
            ->load(['roles', 'socialAccounts', 'deviceTokens', 'district']);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->revealPhone(),
            $this->updateRoles(),
            $this->suspend(),
            $this->reactivate(),
        ];
    }

    private function suspend(): Action
    {
        return Action::make('suspend')
            ->label(__('admin.users.actions.suspend'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize('suspend')
            ->modalDescription(__('admin.users.actions.suspend_help'))
            ->schema([$this->reason()])
            ->action(function (array $data, SuspendUser $suspendUser): void {
                try {
                    $suspendUser->handle($this->user(), $this->admin(), $this->reasonFrom($data));
                } catch (LastSuperAdmin $exception) {
                    $this->refuse($exception);

                    return;
                }

                Notification::make()->success()->title(__('admin.users.actions.suspended'))->send();
            });
    }

    private function reactivate(): Action
    {
        return Action::make('reactivate')
            ->label(__('admin.users.actions.reactivate'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->authorize('reactivate')
            ->schema([$this->reason()])
            ->action(function (array $data, ReactivateUser $reactivateUser): void {
                $reactivateUser->handle($this->user(), $this->admin(), $this->reasonFrom($data));

                Notification::make()->success()->title(__('admin.users.actions.reactivated'))->send();
            });
    }

    private function updateRoles(): Action
    {
        return Action::make('updateRoles')
            ->label(__('admin.users.actions.roles'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('gray')
            ->authorize('updateRoles')
            ->fillForm(fn (): array => ['roles' => $this->user()->getRoleNames()->all()])
            ->schema([
                CheckboxList::make('roles')
                    ->label(__('admin.users.fields.roles'))
                    ->options(array_combine(
                        array_column(AdminRole::cases(), 'value'),
                        array_map(fn (AdminRole $role): string => $role->label(), AdminRole::cases()),
                    )),
                $this->reason()->required(false),
            ])
            ->action(function (array $data, UpdateUserRoles $updateUserRoles): void {
                $names = is_array($data['roles'] ?? null) ? $data['roles'] : [];
                $roles = array_values(array_filter(array_map(fn (mixed $name): ?AdminRole => is_string($name) ? AdminRole::tryFrom($name) : null, $names)));
                $reason = $this->reasonFrom($data);

                try {
                    $updateUserRoles->handle($this->user(), $roles, $this->admin(), $reason === '' ? null : $reason);
                } catch (LastSuperAdmin $exception) {
                    $this->refuse($exception);

                    return;
                }

                Notification::make()->success()->title(__('admin.users.actions.roles_updated'))->send();
            });
    }

    /**
     * The full number, for roles allowed to see it, and an entry in the activity log each time.
     */
    private function revealPhone(): Action
    {
        return Action::make('revealPhone')
            ->label(__('admin.users.actions.reveal_phone'))
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->authorize('revealPhone')
            ->action(function (RecordPhoneReveal $recordPhoneReveal): void {
                $phone = $recordPhoneReveal->handle($this->user(), $this->admin());

                Notification::make()
                    ->info()
                    ->title(__('admin.users.actions.revealed_phone', ['phone' => $phone?->national() ?? '—']))
                    ->persistent()
                    ->send();
            });
    }

    private function reason(): Textarea
    {
        return Textarea::make('reason')->label(__('admin.users.fields.reason'))->required()->maxLength(self::MAX_REASON)->rows(3);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function reasonFrom(array $data): string
    {
        return is_string($data['reason'] ?? null) ? trim($data['reason']) : '';
    }

    private function refuse(LastSuperAdmin $exception): void
    {
        Notification::make()->danger()->title(__($exception->translationKey))->send();
    }

    private function user(): User
    {
        $record = $this->getRecord();

        if (! $record instanceof User) {
            throw new LogicException('This page shows users.');
        }

        return $record;
    }

    private function admin(): User
    {
        $admin = Filament::auth()->user();

        if (! $admin instanceof User) {
            throw new LogicException('An admin is signed in.');
        }

        return $admin;
    }
}
