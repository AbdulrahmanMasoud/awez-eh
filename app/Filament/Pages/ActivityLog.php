<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\Identity\Support\AdminPermissions;
use App\Filament\Navigation\AdminNavigationGroup;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * سجل النشاط: who did what to which record, and when (Cairo time). Read only, for the roles that
 * hold `View:ActivityLog` (super_admin by default).
 */
final class ActivityLog extends Page implements HasTable
{
    use InteractsWithTable;

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::System;

    #[\Override]
    protected static ?int $navigationSort = 3;

    #[\Override]
    protected static ?string $slug = 'activity-log';

    public static function canAccess(): bool
    {
        $admin = Filament::auth()->user();

        return $admin instanceof User && $admin->mayAdminister(AdminPermissions::ACTIVITY_LOG);
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.activity.label');
    }

    public function getTitle(): string
    {
        return (string) __('admin.activity.label');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $timezone = Config::string('awez.display_timezone');

        return $table
            ->query(Activity::query()->with(['causer', 'subject']))
            // Newest first: ids grow with time, and the primary key answers.
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('admin.activity.fields.at'))->dateTime(timezone: $timezone),
                TextColumn::make('causer.name')->label(__('admin.activity.fields.admin'))->placeholder(__('admin.activity.system')),
                TextColumn::make('event')
                    ->label(__('admin.activity.fields.event'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $this->eventLabel($state)),
                TextColumn::make('subject_type')
                    ->label(__('admin.activity.fields.subject'))
                    ->formatStateUsing(fn (Activity $record): string => $this->subjectLabel($record)),
                TextColumn::make('attribute_changes')
                    ->label(__('admin.activity.fields.changes'))
                    ->state(fn (Activity $record): string => $this->changes($record))
                    ->wrap(),
                TextColumn::make('properties.reason')->label(__('admin.activity.fields.reason'))->placeholder('—')->wrap(),
            ])
            ->filters([
                // Each filter is on an index: causer, event, created_at.
                SelectFilter::make('causer_id')
                    ->label(__('admin.activity.fields.admin'))
                    ->options(fn (): array => User::role(array_column(AdminRole::cases(), 'value'))->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, mixed $id): Builder => $query->where('causer_type', new User()->getMorphClass())->where('causer_id', $id),
                    )),
                SelectFilter::make('event')
                    ->label(__('admin.activity.fields.event'))
                    ->options(fn (): array => collect(__('admin.activity.events'))->filter(fn (mixed $label): bool => is_string($label))->all()),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('admin.activity.fields.from')),
                        DatePicker::make('until')->label(__('admin.activity.fields.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, mixed $from): Builder => is_string($from) ? $query->where('created_at', '>=', Date::parse($from, $timezone)->startOfDay()->utc()) : $query)
                        ->when($data['until'] ?? null, fn (Builder $query, mixed $until): Builder => is_string($until) ? $query->where('created_at', '<=', Date::parse($until, $timezone)->endOfDay()->utc()) : $query)),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('log_name', AdminLog::NAME));
    }

    private function eventLabel(?string $event): string
    {
        $label = __("admin.activity.events.{$event}");

        return is_string($label) && $label !== "admin.activity.events.{$event}" ? $label : (string) $event;
    }

    private function subjectLabel(Activity $activity): string
    {
        // Settings have no row of their own: the entry names their group (AdminLog::recordSettings).
        $group = $activity->properties?->get('settings');

        if ($activity->subject_type === null && is_string($group)) {
            $label = __("admin.settings_groups.{$group}");

            return is_string($label) ? $label : $group;
        }

        $type = (string) $activity->subject_type;
        $label = __("admin.activity.subjects.{$type}");
        $subject = $activity->subject;
        $title = $subject?->getAttribute($subject instanceof User ? 'name' : 'name_ar');

        return trim((is_string($label) ? $label : $type).' · '.(is_string($title) ? $title : ''), ' ·');
    }

    /**
     * "status: active → suspended". Only statuses, roles and reference data's own fields are ever
     * logged, never personal data.
     */
    private function changes(Activity $activity): string
    {
        $changes = $activity->attribute_changes ?? new Collection;
        $old = $changes->get('old', []);
        $new = $changes->get('attributes', []);

        if (! is_array($old) || ! is_array($new)) {
            return '';
        }

        return collect(array_keys($new + $old))
            ->map(fn (int|string $key): string => sprintf('%s: %s → %s', $key, $this->value($old[$key] ?? null), $this->value($new[$key] ?? null)))
            ->implode("\n");
    }

    private function value(mixed $value): string
    {
        return match (true) {
            is_array($value) => implode(', ', array_map(strval(...), array_filter($value, is_scalar(...)))),
            is_bool($value) => $value ? '✓' : '✗',
            is_scalar($value) => (string) $value,
            default => '—',
        };
    }
}
