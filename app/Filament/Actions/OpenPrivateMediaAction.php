<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Media\Actions\SignPrivateMediaUrl;
use App\Domains\Media\Models\Media;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Livewire\Component;

/**
 * Opens a private document (an agreement's scan) in a new tab through a signed URL that expires in
 * minutes. The URL is made only when the admin clicks, so each opening, and only that, is logged.
 */
final class OpenPrivateMediaAction
{
    public const string NAME = 'openPrivateMedia';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label(__('admin.media.open_document'))
            ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->visible(fn (Media $record): bool => self::allowed($record))
            ->action(function (Media $record, Component $livewire, SignPrivateMediaUrl $signPrivateMediaUrl): void {
                $admin = Filament::auth()->user();
                abort_unless($admin instanceof User, 403);

                $url = $signPrivateMediaUrl->handle($admin, $record);

                $livewire->js('window.open('.Js::from($url).', "_blank", "noopener")');
            });
    }

    private static function allowed(Media $media): bool
    {
        $admin = Filament::auth()->user();

        return $admin instanceof User && $media->isPrivate() && Gate::forUser($admin)->allows('viewPrivateMedia', $media->model);
    }
}
