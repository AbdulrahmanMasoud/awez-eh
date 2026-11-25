<?php

declare(strict_types=1);

namespace App\Filament\Navigation;

use Filament\Support\Contracts\HasLabel;

/**
 * The sidebar's groups, in ADMIN_PANEL.md's order. A group shows only once something is in it.
 */
enum AdminNavigationGroup: string implements HasLabel
{
    case Content = 'content';
    case Activity = 'activity';
    case System = 'system';

    public function getLabel(): string
    {
        return (string) __("admin.navigation.{$this->value}");
    }
}
