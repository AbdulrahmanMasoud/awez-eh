<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domains\Identity\Models\User;
use App\Filament\Pages\ReviewQueue;
use App\Filament\Review\ReviewableKind;
use App\Filament\Review\ReviewKinds;
use App\Filament\Review\ReviewTable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * محتاج قرارك: the oldest items waiting for review, a button per kind with its count, approve and
 * reject in place, and a link to المراجعة. For admins who review.
 */
final class PendingReview extends TableWidget
{
    private const int ROWS = 5;

    #[\Override]
    protected static ?int $sort = 2;

    #[\Override]
    protected int|string|array $columnSpan = 'full';

    public ?string $kind = null;

    public static function canView(): bool
    {
        return ReviewQueue::canAccess();
    }

    public function table(Table $table): Table
    {
        $kinds = $this->kinds();
        $active = $this->activeKind();

        return ReviewTable::configure($table, $active, self::ROWS)
            ->heading(__('admin.review.widget'))
            ->headerActions([
                ...array_map(fn (ReviewableKind $kind): Action => Action::make("kind_{$kind->key()}")
                    ->label($kind->label())
                    ->badge((string) $kind->pending()->count())
                    ->color($kind->key() === $active?->key() ? 'primary' : 'gray')
                    ->action(function () use ($kind): void {
                        $this->kind = $kind->key();
                        $this->resetTable();
                    }), array_values($kinds)),
                Action::make('all')
                    ->label(__('admin.review.see_all'))
                    ->link()
                    ->url(ReviewQueue::getUrl(['kind' => $active?->key()])),
            ]);
    }

    /**
     * @return array<string, ReviewableKind>
     */
    private function kinds(): array
    {
        $admin = Filament::auth()->user();

        return $admin instanceof User ? resolve(ReviewKinds::class)->for($admin) : [];
    }

    private function activeKind(): ?ReviewableKind
    {
        $admin = Filament::auth()->user();

        return $admin instanceof User ? resolve(ReviewKinds::class)->find($admin, $this->kind) : null;
    }
}
