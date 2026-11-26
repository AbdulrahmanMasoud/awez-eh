<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Exceptions\InvalidPhoneNumber;
use App\Filament\Navigation\AdminNavigationGroup;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Support\MediaFields;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use UnitEnum;

/**
 * المستخدمين: a list and each account's page. No create, no edit of personal data and no
 * delete (residents own their accounts, task 005); admins suspend, reactivate, change roles and
 * may look at a number, each through an Identity Action and into the activity log.
 */
final class UserResource extends Resource
{
    #[\Override]
    protected static ?string $model = User::class;

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::System;

    #[\Override]
    protected static ?int $navigationSort = 1;

    #[\Override]
    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return (string) __('admin.users.label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.users.plural');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    /**
     * A number typed with spaces is one term, not three.
     */
    public static function shouldSplitGlobalSearchTerms(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return $record instanceof User ? array_filter([
            __('admin.users.fields.phone') => $record->phone?->mask(),
            __('admin.users.fields.email') => $record->email,
        ], fn (?string $value): bool => $value !== null) : [];
    }

    /**
     * A phone number typed any way (`0101 234 5678`, `+20 10…`, Arabic-Indic digits) finds its
     * account by the stored E.164 form, which the unique index answers. Anything else searches
     * names and emails.
     *
     * @param  Builder<Model>  $query
     */
    protected static function applyGlobalSearchAttributeConstraints(Builder $query, string $search): void
    {
        $phone = self::phoneIn($search);

        if ($phone instanceof PhoneNumber) {
            $query->where('phone', $phone->e164());

            return;
        }

        parent::applyGlobalSearchAttributeConstraints($query, $search);
    }

    public static function phoneIn(string $search): ?PhoneNumber
    {
        try {
            return PhoneNumber::from($search);
        } catch (InvalidPhoneNumber) {
            return null;
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.users.fields.name'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::search($query, $search)),
                TextColumn::make('phone')
                    ->label(__('admin.users.fields.phone'))
                    ->formatStateUsing(fn (?PhoneNumber $state): ?string => $state?->mask())
                    // A number reads left to right inside Arabic text.
                    ->extraAttributes(['dir' => 'ltr'])
                    ->placeholder('—'),
                TextColumn::make('email')->label(__('admin.users.fields.email'))->placeholder('—'),
                self::statusColumn(),
                TextColumn::make('roles.name')
                    ->label(__('admin.users.fields.roles'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AdminRole::tryFrom($state)?->label() ?? $state),
                // Newest first by id: UUID v7 keys are time-ordered (DECISIONS 53).
                TextColumn::make('created_at')
                    ->label(__('admin.users.fields.joined'))
                    ->date(timezone: Config::string('awez.display_timezone'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('id', $direction === 'asc' ? 'asc' : 'desc')),
                TextColumn::make('last_seen_at')
                    ->label(__('admin.users.fields.last_seen'))
                    ->since()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            // A number typed with spaces is one term, not three.
            ->splitSearchTerms(false)
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.users.fields.status'))
                    ->options(self::statusOptions()),
                TernaryFilter::make('has_phone')
                    ->label(__('admin.users.filters.has_phone'))
                    ->nullable()
                    ->attribute('phone'),
                TernaryFilter::make('is_provider')
                    ->label(__('admin.users.filters.is_provider'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('provider'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('provider'),
                    ),
                TernaryFilter::make('is_advertiser')
                    ->label(__('admin.users.filters.is_advertiser'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query->whereHas('properties')->orWhereHas('usedItems')->orWhereHas('places')),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('properties')->whereDoesntHave('usedItems')->whereDoesntHave('places'),
                    ),
                TernaryFilter::make('is_admin')
                    ->label(__('admin.users.filters.is_admin'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('roles'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('roles'),
                    ),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.users.sections.profile'))->columns(2)->schema([
                // The picture the resident uploaded; the Google or Apple one is not stored here.
                MediaFields::entry(User::AVATAR)
                    ->label(__('admin.users.fields.avatar'))
                    ->circular()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('name')->label(__('admin.users.fields.name')),
                TextEntry::make('phone')
                    ->label(__('admin.users.fields.phone'))
                    ->formatStateUsing(fn (?PhoneNumber $state): ?string => $state?->mask())
                    ->extraAttributes(['dir' => 'ltr'])
                    ->placeholder('—'),
                TextEntry::make('email')->label(__('admin.users.fields.email'))->placeholder('—'),
                TextEntry::make('status')
                    ->label(__('admin.users.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => (string) __("admin.users.statuses.{$state->value}"))
                    ->color(fn (UserStatus $state): string => self::statusColor($state)),
                TextEntry::make('suspended_at')
                    ->label(__('admin.users.fields.suspended_at'))
                    ->dateTime(timezone: Config::string('awez.display_timezone'))
                    ->visible(fn (User $record): bool => $record->suspended_at !== null),
                TextEntry::make('roles.name')
                    ->label(__('admin.users.fields.roles'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AdminRole::tryFrom($state)?->label() ?? $state)
                    ->placeholder('—'),
                TextEntry::make('locale')->label(__('admin.users.fields.locale'))->formatStateUsing(fn (BackedEnum $state): string => (string) $state->value),
                TextEntry::make('district.name_ar')->label(__('admin.users.fields.district'))->placeholder('—'),
                TextEntry::make('created_at')->label(__('admin.users.fields.joined'))->dateTime(timezone: Config::string('awez.display_timezone')),
                TextEntry::make('last_seen_at')->label(__('admin.users.fields.last_seen'))->since()->placeholder('—'),
            ]),
            Section::make(__('admin.users.sections.sign_in'))->columns(2)->schema([
                TextEntry::make('socialAccounts.provider')
                    ->label(__('admin.users.fields.providers'))
                    ->badge()
                    ->formatStateUsing(fn (BackedEnum $state): string => ucfirst((string) $state->value))
                    ->placeholder(__('admin.users.no_providers')),
                RepeatableEntry::make('deviceTokens')
                    ->label(__('admin.users.fields.devices'))
                    ->schema([
                        TextEntry::make('platform')->hiddenLabel()->formatStateUsing(fn (BackedEnum $state): string => (string) $state->value),
                        TextEntry::make('app_version')->hiddenLabel()->placeholder('—'),
                        TextEntry::make('last_used_at')->hiddenLabel()->since(),
                    ])
                    ->columns(3)
                    ->placeholder(__('admin.users.no_devices')),
            ]),
            Section::make(__('admin.users.sections.content'))->columns(4)->schema([
                TextEntry::make('properties_count')->label(__('admin.users.content.properties')),
                TextEntry::make('used_items_count')->label(__('admin.users.content.used_items')),
                TextEntry::make('places_count')->label(__('admin.users.content.places')),
                TextEntry::make('service_requests_count')->label(__('admin.users.content.requests')),
                TextEntry::make('request_replies_count')->label(__('admin.users.content.request_replies')),
                TextEntry::make('resident_bookings_count')->label(__('admin.users.content.bookings')),
                TextEntry::make('reviews_count')->label(__('admin.users.content.reviews')),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label(__('admin.users.fields.status'))
            ->badge()
            ->formatStateUsing(fn (UserStatus $state): string => (string) __("admin.users.statuses.{$state->value}"))
            ->color(fn (UserStatus $state): string => self::statusColor($state));
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return array_combine(
            array_column(UserStatus::cases(), 'value'),
            array_map(fn (UserStatus $status): string => (string) __("admin.users.statuses.{$status->value}"), UserStatus::cases()),
        );
    }

    /**
     * Search by a number through the unique index; otherwise by email, or a part of the name.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function search(Builder $query, string $search): Builder
    {
        $phone = self::phoneIn($search);

        if ($phone instanceof PhoneNumber) {
            return $query->where('phone', $phone->e164());
        }

        return $query->where(fn (Builder $query): Builder => $query
            ->where('email', mb_strtolower(trim($search)))
            ->orWhere('name', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%'));
    }

    private static function statusColor(UserStatus $status): string
    {
        return match ($status) {
            UserStatus::Active => 'success',
            UserStatus::Suspended => 'danger',
            UserStatus::Deleted => 'gray',
        };
    }
}
