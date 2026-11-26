<?php

declare(strict_types=1);

namespace App\Filament\Resources\Properties\Pages;

use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Actions\CreateBrokeredProperty;
use App\Domains\RealEstate\Exceptions\PlatformAccountMissing;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * إضافة عقار: a sales admin lists a brokered property for an owner who signed an agreement. It
 * waits for review like any listing; its photos are cleaned on the way in (AddImage).
 */
final class CreateProperty extends CreateRecord
{
    #[\Override]
    protected static string $resource = PropertyResource::class;

    #[\Override]
    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return (string) __('admin.properties.create_brokered');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $admin = Filament::auth()->user();

        if (! $admin instanceof User) {
            throw new LogicException('An admin is signed in.');
        }

        $ownerId = $data['owner_id'] ?? null;
        $owner = is_string($ownerId) ? User::query()->find($ownerId) : null;

        try {
            return resolve(CreateBrokeredProperty::class)->handle(
                $admin,
                PropertyResource::propertyFrom($data),
                $owner,
                PropertyResource::agreementFrom($data),
            );
        } catch (PlatformAccountMissing $exception) {
            Notification::make()->danger()->title(__($exception->translationKey))->persistent()->send();

            throw new Halt($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}
