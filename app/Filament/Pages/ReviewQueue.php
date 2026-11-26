<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminPermissions;
use App\Filament\Review\ReviewableKind;
use App\Filament\Review\ReviewKinds;
use App\Filament\Review\ReviewTable;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Url;

/**
 * المراجعة: everything waiting for review, oldest first, a tab per kind with its count. Kinds
 * register in ReviewKinds; this page never names one. Moderators and super admins only.
 */
final class ReviewQueue extends Page implements HasTable
{
    use InteractsWithTable;

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    #[\Override]
    protected static ?int $navigationSort = 2;

    #[\Override]
    protected static ?string $slug = 'review';

    #[Url]
    public ?string $kind = null;

    public static function canAccess(): bool
    {
        $admin = Filament::auth()->user();

        return $admin instanceof User && $admin->mayAdminister(AdminPermissions::REVIEW_QUEUE);
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.review.label');
    }

    public static function getNavigationBadge(): ?string
    {
        $admin = Filament::auth()->user();
        $count = $admin instanceof User ? resolve(ReviewKinds::class)->count($admin) : 0;

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public function getTitle(): string
    {
        return (string) __('admin.review.label');
    }

    public function updatedKind(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return ReviewTable::configure($table, $this->activeKind());
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make(__('admin.review.label'))
                ->key('reviewKinds')
                ->livewireProperty('kind')
                ->tabPanels(false)
                ->contained(false)
                ->tabs($this->tabs()),
            EmbeddedTable::make(),
        ]);
    }

    /**
     * @return array<string, Tab>
     */
    private function tabs(): array
    {
        return array_map(
            fn (ReviewableKind $kind): Tab => Tab::make($kind->label())->badge($kind->pending()->count())->badgeColor('warning'),
            resolve(ReviewKinds::class)->for($this->admin()),
        );
    }

    private function activeKind(): ?ReviewableKind
    {
        $kind = resolve(ReviewKinds::class)->find($this->admin(), $this->kind);
        $this->kind = $kind?->key();

        return $kind;
    }

    private function admin(): User
    {
        $admin = Filament::auth()->user();
        abort_unless($admin instanceof User, 403);

        return $admin;
    }
}
