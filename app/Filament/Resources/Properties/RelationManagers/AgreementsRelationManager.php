<?php

declare(strict_types=1);

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Domains\Identity\Models\User;
use App\Domains\Media\Actions\SignPrivateMediaUrl;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Settings\MediaSettings;
use App\Domains\Media\Support\MediaDisks;
use App\Domains\Media\Support\MediaTypes;
use App\Domains\RealEstate\Actions\RecordPropertyAgreement;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Filament\Resources\Properties\PropertyResource;
use App\Support\PhoneNumber;
use Brick\Money\Money;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

/**
 * الاتفاقيات on a property's page (sales and super admins): record one, which makes the property
 * brokered, and open one with its scanned documents. Never edited or deleted: they are history.
 */
final class AgreementsRelationManager extends RelationManager
{
    private const string DOCUMENTS = 'documents';

    #[\Override]
    protected static string $relationship = 'agreements';

    /**
     * On the property's view page, where relation managers are read-only by default: recording an
     * agreement is what this manager is for. Editing and deleting stay refused by the policy.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string) __('admin.agreements.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...PropertyResource::agreementFields(),
            // Scans stay private: no URL, only a signed one per opening (task 007).
            SpatieMediaLibraryFileUpload::make('documents')
                ->label(__('admin.agreements.fields.documents'))
                ->collection(self::DOCUMENTS)
                ->disk(MediaDisks::private())
                ->visibility('private')
                ->multiple()
                ->maxFiles(fn (): int => resolve(MediaSettings::class)->agreement_documents)
                ->acceptedFileTypes(MediaTypes::DOCUMENTS)
                ->maxSize(fn (): int => resolve(MediaSettings::class)->maxFileSizeKilobytes()),
        ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('owner_name')->label(__('admin.agreements.fields.owner_name')),
            TextEntry::make('owner_phone')->label(__('admin.agreements.fields.owner_phone'))->formatStateUsing(fn (?PhoneNumber $state): ?string => $state?->mask()),
            TextEntry::make('commission')->label(__('admin.agreements.fields.commission'))->state(fn (PropertyAgreement $record): string => $this->commission($record)),
            TextEntry::make('signed_on')->label(__('admin.agreements.fields.signed_on'))->date(),
            TextEntry::make('ends_on')->label(__('admin.agreements.fields.ends_on'))->date()->placeholder('—'),
            TextEntry::make('creator.name')->label(__('admin.agreements.fields.created_by'))->placeholder('—'),
            TextEntry::make('notes')->label(__('admin.agreements.fields.notes'))->placeholder('—')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('owner_name')->label(__('admin.agreements.fields.owner_name')),
                TextColumn::make('owner_phone')->label(__('admin.agreements.fields.owner_phone'))->formatStateUsing(fn (?PhoneNumber $state): ?string => $state?->mask()),
                TextColumn::make('commission')->label(__('admin.agreements.fields.commission'))->state(fn (PropertyAgreement $record): string => $this->commission($record)),
                TextColumn::make('signed_on')->label(__('admin.agreements.fields.signed_on'))->date(),
                TextColumn::make('ends_on')->label(__('admin.agreements.fields.ends_on'))->date()->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('admin.agreements.create'))
                    ->using(
                        function (array $data): Model {
                            $owner = $this->getOwnerRecord();
                            $admin = Filament::auth()->user();

                            if (! $owner instanceof Property || ! $admin instanceof User) {
                                throw new LogicException('Agreements belong to a property, recorded by an admin.');
                            }

                            return resolve(RecordPropertyAgreement::class)->handle($owner, $admin, PropertyResource::agreementFrom($data));
                        }),
            ])
            ->recordActions([
                ViewAction::make(),
                $this->openDocument(),
            ]);
    }

    /**
     * One of the agreement's scans, chosen by its position, opened in a new tab through a signed
     * URL made on the click (and logged by SignPrivateMediaUrl).
     */
    private function openDocument(): Action
    {
        return Action::make('openDocument')
            ->label(__('admin.media.open_document'))
            ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->visible(fn (PropertyAgreement $record): bool => $record->getMedia(self::DOCUMENTS)->isNotEmpty()
                && Gate::allows('viewPrivateMedia', $record))
            ->schema(fn (PropertyAgreement $record): array => [
                Select::make('document')
                    ->label(__('admin.agreements.fields.documents'))
                    ->options($this->documentOptions($record))
                    ->required(),
            ])
            ->action(function (PropertyAgreement $record, array $data, SignPrivateMediaUrl $signPrivateMediaUrl): void {
                $admin = Filament::auth()->user();
                $media = $record->getMedia(self::DOCUMENTS)->first(fn (SpatieMedia $media): bool => $media instanceof Media && $media->id === ($data['document'] ?? null));
                abort_unless($admin instanceof User && $media instanceof Media, 404);

                $this->js('window.open('.Js::from($signPrivateMediaUrl->handle($admin, $media)).', "_blank", "noopener")');
            });
    }

    /**
     * @return array<string, string>
     */
    private function documentOptions(PropertyAgreement $agreement): array
    {
        $options = [];

        foreach ($agreement->getMedia(self::DOCUMENTS)->values() as $index => $media) {
            if ($media instanceof Media) {
                $options[$media->id] = (string) __('admin.agreements.document', ['number' => $index + 1]);
            }
        }

        return $options;
    }

    private function commission(PropertyAgreement $agreement): string
    {
        $fixed = $agreement->commission_fixed;

        if ($fixed instanceof Money) {
            return PropertyResource::money($fixed);
        }

        return (string) __('admin.agreements.rate', ['rate' => number_format(($agreement->commission_rate_basis_points ?? 0) / 100, 2)]);
    }
}
