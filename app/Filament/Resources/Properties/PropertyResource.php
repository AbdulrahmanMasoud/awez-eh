<?php

declare(strict_types=1);

namespace App\Filament\Resources\Properties;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Moderation\Support\ContentLog;
use App\Domains\RealEstate\Data\NewAgreement;
use App\Domains\RealEstate\Enums\Finishing;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Enums\PropertyType;
use App\Domains\RealEstate\Enums\RentPeriod;
use App\Domains\RealEstate\Models\Property;
use App\Exceptions\InvalidPhoneNumber;
use App\Filament\Navigation\AdminNavigationGroup;
use App\Filament\Resources\Properties\Pages\CreateProperty;
use App\Filament\Resources\Properties\Pages\ListProperties;
use App\Filament\Resources\Properties\Pages\ViewProperty;
use App\Filament\Resources\Properties\RelationManagers\AgreementsRelationManager;
use App\Filament\Support\MediaFields;
use App\Support\AdminNumbers;
use App\Support\Geo\Point;
use App\Support\PhoneNumber;
use BackedEnum;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Closure;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * العقارات under المحتوى: every property, its page with photos, point, owner, status history and
 * the admin moves the status machine allows, and (sales) brokered properties and their agreements.
 * Admins never edit residents' text: a moderator who wants a change rejects with a reason.
 */
final class PropertyResource extends Resource
{
    private const int MAX_TITLE = 120;

    private const int MAX_DESCRIPTION = 5000;

    private const int BASIS_POINTS_PER_PERCENT = 100;

    #[\Override]
    protected static ?string $model = Property::class;

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Content;

    #[\Override]
    protected static ?int $navigationSort = 1;

    #[\Override]
    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return (string) __('admin.properties.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.properties.plural');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['media', 'user', 'district']))
            ->columns([
                MediaFields::column(Property::PHOTOS)->label(__('admin.properties.fields.photos')),
                TextColumn::make('title')->label(__('admin.properties.fields.title'))->limit(50)->wrap(),
                TextColumn::make('type')->label(__('admin.properties.fields.type'))->formatStateUsing(fn (PropertyType $state): string => self::label('types', $state))->badge()->color('gray'),
                TextColumn::make('purpose')->label(__('admin.properties.fields.purpose'))->formatStateUsing(fn (PropertyPurpose $state): string => self::label('purposes', $state)),
                TextColumn::make('price')->label(__('admin.properties.fields.price'))->formatStateUsing(fn (?Money $state): string => self::money($state)),
                TextColumn::make('district.name_ar')->label(__('admin.properties.fields.district')),
                TextColumn::make('user.name')->label(__('admin.properties.fields.owner')),
                IconColumn::make('brokered')->label(__('admin.properties.fields.brokered'))->boolean(),
                self::statusColumn(),
                // Newest first by id: UUID v7 keys are time-ordered (DECISIONS 53).
                TextColumn::make('created_at')->label(__('admin.properties.fields.created_at'))->since()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('id', $direction === 'asc' ? 'asc' : 'desc')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('purpose')->label(__('admin.properties.fields.purpose'))->options(self::options('purposes', PropertyPurpose::cases())),
                SelectFilter::make('type')->label(__('admin.properties.fields.type'))->options(self::options('types', PropertyType::cases()))->multiple(),
                SelectFilter::make('district')->label(__('admin.properties.fields.district'))->relationship('district', 'name_ar'),
                TernaryFilter::make('brokered')->label(__('admin.properties.fields.brokered')),
                SelectFilter::make('owner')->label(__('admin.properties.fields.owner'))->relationship('user', 'name')->searchable(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.properties.sections.photos'))->schema([
                MediaFields::entry(Property::PHOTOS)->hiddenLabel()->placeholder(__('admin.properties.no_photos')),
            ])->columnSpanFull(),
            Section::make(__('admin.properties.sections.details'))->columns(3)->schema([
                TextEntry::make('title')->label(__('admin.properties.fields.title'))->columnSpanFull(),
                TextEntry::make('purpose')->label(__('admin.properties.fields.purpose'))->formatStateUsing(fn (PropertyPurpose $state): string => self::label('purposes', $state)),
                TextEntry::make('type')->label(__('admin.properties.fields.type'))->formatStateUsing(fn (PropertyType $state): string => self::label('types', $state)),
                TextEntry::make('price')->label(__('admin.properties.fields.price'))->formatStateUsing(fn (?Money $state): string => self::money($state)),
                TextEntry::make('rent_period')->label(__('admin.properties.fields.rent_period'))->formatStateUsing(fn (?RentPeriod $state): string => $state instanceof RentPeriod ? self::label('rent_periods', $state) : '—')->placeholder('—'),
                TextEntry::make('area_m2')->label(__('admin.properties.fields.area_m2'))->numeric(),
                TextEntry::make('bedrooms')->label(__('admin.properties.fields.bedrooms'))->placeholder('—'),
                TextEntry::make('bathrooms')->label(__('admin.properties.fields.bathrooms'))->placeholder('—'),
                TextEntry::make('floor')->label(__('admin.properties.fields.floor'))->placeholder('—'),
                IconEntry::make('furnished')->label(__('admin.properties.fields.furnished'))->boolean(),
                TextEntry::make('finishing')->label(__('admin.properties.fields.finishing'))->formatStateUsing(fn (?Finishing $state): string => $state instanceof Finishing ? self::label('finishings', $state) : '—')->placeholder('—'),
                TextEntry::make('district.name_ar')->label(__('admin.properties.fields.district')),
                TextEntry::make('address_note')->label(__('admin.properties.fields.address_note'))->placeholder('—'),
                TextEntry::make('description')->label(__('admin.properties.fields.description'))->columnSpanFull(),
            ]),
            Section::make(__('admin.properties.sections.location'))->columns(2)->schema([
                TextEntry::make('latitude')->label(__('admin.fields.latitude'))->state(fn (Property $record): ?float => $record->location?->latitude),
                TextEntry::make('longitude')->label(__('admin.fields.longitude'))->state(fn (Property $record): ?float => $record->location?->longitude),
            ]),
            Section::make(__('admin.properties.sections.owner'))->columns(3)->schema([
                TextEntry::make('user.name')->label(__('admin.properties.fields.owner')),
                // Masked everywhere; the full number only through "show number", logged.
                TextEntry::make('owner_phone')
                    ->label(__('admin.properties.fields.owner_phone'))
                    ->state(fn (Property $record): ?string => self::maskedOwnerPhone($record))
                    ->placeholder('—'),
                IconEntry::make('brokered')->label(__('admin.properties.fields.brokered'))->boolean(),
            ]),
            Section::make(__('admin.properties.sections.status'))->columns(3)->schema([
                TextEntry::make('status')
                    ->label(__('admin.properties.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (ContentStatus $state): string => self::statusLabel($state))
                    ->color(fn (ContentStatus $state): string => self::statusColor($state)),
                TextEntry::make('rejection_reason')->label(__('admin.properties.fields.rejection_reason'))->placeholder('—'),
                TextEntry::make('views_count')->label(__('admin.properties.fields.views_count'))->numeric(),
                TextEntry::make('reviewer.name')->label(__('admin.properties.fields.reviewer'))->placeholder('—'),
                TextEntry::make('reviewed_at')->label(__('admin.properties.fields.reviewed_at'))->dateTime(timezone: Config::string('awez.display_timezone'))->placeholder('—'),
                TextEntry::make('published_at')->label(__('admin.properties.fields.published_at'))->dateTime(timezone: Config::string('awez.display_timezone'))->placeholder('—'),
            ]),
            Section::make(__('admin.properties.sections.history'))->schema([
                RepeatableEntry::make('history')
                    ->hiddenLabel()
                    ->state(fn (Property $record): array => self::history($record))
                    ->schema([
                        TextEntry::make('event')->hiddenLabel()->badge(),
                        TextEntry::make('change')->hiddenLabel(),
                        TextEntry::make('by')->hiddenLabel(),
                        TextEntry::make('at')->hiddenLabel(),
                        TextEntry::make('reason')->hiddenLabel()->placeholder('—'),
                    ])
                    ->columns(5)
                    ->placeholder(__('admin.properties.no_history')),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * The form of a brokered property a sales admin lists: the property, its owner's account (none
     * for the platform account) and the agreement.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.properties.sections.details'))->columns(3)->schema([
                Select::make('purpose')->label(__('admin.properties.fields.purpose'))->options(self::options('purposes', PropertyPurpose::cases()))->required()->live(),
                Select::make('type')->label(__('admin.properties.fields.type'))->options(self::options('types', PropertyType::cases()))->required(),
                TextInput::make('price')->label(__('admin.properties.fields.price'))->numeric()->minValue(1)->step('0.01')->required()->suffix(__('admin.properties.currency')),
                Select::make('rent_period')->label(__('admin.properties.fields.rent_period'))->options(self::options('rent_periods', RentPeriod::cases()))
                    ->visible(fn (Get $get): bool => $get('purpose') === PropertyPurpose::Rent->value)
                    ->required(fn (Get $get): bool => $get('purpose') === PropertyPurpose::Rent->value),
                TextInput::make('title')->label(__('admin.properties.fields.title'))->required()->minLength(5)->maxLength(self::MAX_TITLE)->columnSpanFull(),
                Textarea::make('description')->label(__('admin.properties.fields.description'))->required()->minLength(10)->maxLength(self::MAX_DESCRIPTION)->rows(4)->columnSpanFull(),
                TextInput::make('area_m2')->label(__('admin.properties.fields.area_m2'))->integer()->minValue(1)->required(),
                TextInput::make('bedrooms')->label(__('admin.properties.fields.bedrooms'))->integer()->minValue(0),
                TextInput::make('bathrooms')->label(__('admin.properties.fields.bathrooms'))->integer()->minValue(0),
                TextInput::make('floor')->label(__('admin.properties.fields.floor'))->integer(),
                Select::make('finishing')->label(__('admin.properties.fields.finishing'))->options(self::options('finishings', Finishing::cases())),
                Toggle::make('furnished')->label(__('admin.properties.fields.furnished')),
                Select::make('district_id')->label(__('admin.properties.fields.district'))->relationship('district', 'name_ar')->required()->preload(),
                TextInput::make('address_note')->label(__('admin.properties.fields.address_note'))->maxLength(255)->columnSpan(2),
            ]),
            Section::make(__('admin.properties.sections.location'))->schema([
                Grid::make(2)->schema([
                    TextInput::make('latitude')->label(__('admin.fields.latitude'))->numeric()->minValue(-90)->maxValue(90)->required(),
                    TextInput::make('longitude')->label(__('admin.fields.longitude'))->numeric()->minValue(-180)->maxValue(180)->required(),
                ]),
            ]),
            Section::make(__('admin.properties.sections.photos'))->schema([
                MediaFields::images(Property::PHOTOS)->hiddenLabel(),
            ]),
            Section::make(__('admin.properties.sections.owner'))->schema([
                Select::make('owner_id')
                    ->label(__('admin.properties.fields.owner_account'))
                    ->helperText(__('admin.properties.owner_account_help'))
                    ->options(fn (): array => [])
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->where('name', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%')
                        ->limit(20)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value) ? User::query()->find($value)?->name : null)
                    ->searchable(),
            ]),
            Section::make(__('admin.properties.sections.agreement'))->schema(self::agreementFields()),
        ]);
    }

    /**
     * An agreement's fields, on the brokered property's form and in the agreements' manager.
     *
     * @return list<Grid>
     */
    public static function agreementFields(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('agreement_owner_name')->label(__('admin.agreements.fields.owner_name'))->required()->maxLength(255),
                TextInput::make('agreement_owner_phone')->label(__('admin.agreements.fields.owner_phone'))->required()->tel()
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        try {
                            PhoneNumber::fromMobileOrLandline(is_string($value) ? $value : '');
                        } catch (InvalidPhoneNumber) {
                            $fail((string) __('errors.invalid_phone_number'));
                        }
                    }),
                Select::make('agreement_commission_kind')->label(__('admin.agreements.fields.commission_kind'))
                    ->options(['rate' => __('admin.agreements.commission.rate'), 'fixed' => __('admin.agreements.commission.fixed')])
                    ->required()->live(),
                TextInput::make('agreement_commission_rate_percent')->label(__('admin.agreements.fields.rate_percent'))->numeric()->minValue(0.01)->maxValue(100)->step('0.01')->suffix('%')
                    ->visible(fn (Get $get): bool => $get('agreement_commission_kind') === 'rate')
                    ->required(fn (Get $get): bool => $get('agreement_commission_kind') === 'rate'),
                TextInput::make('agreement_commission_fixed')->label(__('admin.agreements.fields.fixed'))->numeric()->minValue(1)->step('0.01')->suffix(__('admin.properties.currency'))
                    ->visible(fn (Get $get): bool => $get('agreement_commission_kind') === 'fixed')
                    ->required(fn (Get $get): bool => $get('agreement_commission_kind') === 'fixed'),
                DatePicker::make('agreement_signed_on')->label(__('admin.agreements.fields.signed_on'))->required()->native(false),
                DatePicker::make('agreement_ends_on')->label(__('admin.agreements.fields.ends_on'))->afterOrEqual('agreement_signed_on')->native(false),
                Textarea::make('agreement_notes')->label(__('admin.agreements.fields.notes'))->maxLength(2000)->rows(3)->columnSpanFull(),
            ]),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function agreementFrom(array $data): NewAgreement
    {
        $string = fn (string $key): string => is_scalar($data[$key] ?? null) ? trim((string) $data[$key]) : '';
        $rate = $string('agreement_commission_rate_percent');
        $fixed = $string('agreement_commission_fixed');
        $isRate = $string('agreement_commission_kind') === 'rate';

        return new NewAgreement(
            ownerName: $string('agreement_owner_name'),
            ownerPhone: PhoneNumber::fromMobileOrLandline($string('agreement_owner_phone')),
            // A percent with up to two decimals is a whole number of basis points (2.5% is 250).
            commissionRateBasisPoints: $isRate ? BigDecimal::of($rate)->multipliedBy(self::BASIS_POINTS_PER_PERCENT)->toScale(0, RoundingMode::HalfUp)->toInt() : null,
            commissionFixed: $isRate || $fixed === '' ? null : Money::of($fixed, 'EGP'),
            signedOn: Date::parse($string('agreement_signed_on'))->startOfDay(),
            endsOn: $string('agreement_ends_on') === '' ? null : Date::parse($string('agreement_ends_on'))->startOfDay(),
            notes: $string('agreement_notes') === '' ? null : $string('agreement_notes'),
        );
    }

    /**
     * The property's own columns from the brokered property's form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function propertyFrom(array $data): array
    {
        $int = fn (string $key): ?int => is_numeric($data[$key] ?? null) ? (int) $data[$key] : null;
        $string = fn (string $key): ?string => is_scalar($data[$key] ?? null) && trim((string) $data[$key]) !== '' ? trim((string) $data[$key]) : null;
        $purpose = PropertyPurpose::from((string) $string('purpose'));

        return [
            'purpose' => $purpose,
            'type' => PropertyType::from((string) $string('type')),
            'title' => (string) $string('title'),
            'description' => (string) $string('description'),
            'price' => Money::of((string) $string('price'), 'EGP'),
            'rent_period' => $purpose === PropertyPurpose::Rent ? RentPeriod::from((string) $string('rent_period')) : null,
            'area_m2' => $int('area_m2'),
            'bedrooms' => $int('bedrooms'),
            'bathrooms' => $int('bathrooms'),
            'floor' => $int('floor'),
            'furnished' => (bool) ($data['furnished'] ?? false),
            'finishing' => Finishing::tryFrom((string) $string('finishing')),
            'district_id' => $string('district_id'),
            'address_note' => $string('address_note'),
            'location' => new Point((float) $string('latitude'), (float) $string('longitude')),
        ];
    }

    public static function getRelations(): array
    {
        return [AgreementsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProperties::route('/'),
            'create' => CreateProperty::route('/create'),
            'view' => ViewProperty::route('/{record}'),
        ];
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label(__('admin.properties.fields.status'))
            ->badge()
            ->formatStateUsing(fn (ContentStatus $state): string => self::statusLabel($state))
            ->color(fn (ContentStatus $state): string => self::statusColor($state));
    }

    public static function statusLabel(ContentStatus $status): string
    {
        return (string) __("admin.content_statuses.{$status->value}");
    }

    public static function money(?Money $money): string
    {
        return $money instanceof Money ? $money->formatToLocale(AdminNumbers::localeFor(app()->getLocale())) : '—';
    }

    /**
     * The status changes from both logs (moderators' and owners'), newest first.
     *
     * @return list<array<string, string|null>>
     */
    public static function history(Property $property): array
    {
        return array_values(Activity::query()
            ->whereIn('log_name', ContentLog::LOGS)
            ->where('subject_type', $property->getMorphClass())
            ->where('subject_id', $property->id)
            ->with('causer')
            ->latest('id')
            ->get()
            ->map(function (Activity $entry): array {
                $old = $entry->attribute_changes?->get('old');
                $new = $entry->attribute_changes?->get('attributes');
                $from = is_array($old) && is_string($old['status'] ?? null) ? ContentStatus::tryFrom($old['status']) : null;
                $to = is_array($new) && is_string($new['status'] ?? null) ? ContentStatus::tryFrom($new['status']) : null;
                $causer = $entry->causer;
                $created = $entry->created_at;

                return [
                    'event' => (string) __('admin.activity.events.'.$entry->event),
                    'change' => $from instanceof ContentStatus && $to instanceof ContentStatus
                        ? (string) __('admin.properties.status_change', ['from' => self::statusLabel($from), 'to' => self::statusLabel($to)])
                        : null,
                    'by' => $causer instanceof User ? $causer->name : null,
                    'at' => $created instanceof Carbon ? $created->setTimezone(Config::string('awez.display_timezone'))->format('Y-m-d H:i') : null,
                    'reason' => is_string($entry->properties?->get('reason')) ? $entry->properties->get('reason') : null,
                ];
            })
            ->all());
    }

    private static function maskedOwnerPhone(Property $property): ?string
    {
        if ($property->brokered) {
            return $property->agreements()->latest('signed_on')->latest('id')->first()?->owner_phone?->mask();
        }

        return $property->user?->phone?->mask();
    }

    private static function statusColor(ContentStatus $status): string
    {
        return match ($status) {
            ContentStatus::PendingReview => 'warning',
            ContentStatus::Published => 'success',
            ContentStatus::Rejected => 'danger',
            ContentStatus::Draft, ContentStatus::Paused, ContentStatus::Sold, ContentStatus::Rented, ContentStatus::Archived => 'gray',
        };
    }

    private static function label(string $group, BackedEnum $case): string
    {
        return (string) __("real_estate.{$group}.{$case->value}");
    }

    /**
     * @param  list<BackedEnum>  $cases
     * @return array<string, string>
     */
    private static function options(string $group, array $cases): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[(string) $case->value] = self::label($group, $case);
        }

        return $options;
    }
}
