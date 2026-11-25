<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

/**
 * The admin roles (ARCHITECTURE §4), held through spatie/laravel-permission. A user without one
 * is a resident and never enters the panel.
 */
enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Moderator = 'moderator';
    case DataEntry = 'data_entry';
    case Sales = 'sales';

    public function label(): string
    {
        return (string) __("admin.roles.{$this->value}");
    }
}
