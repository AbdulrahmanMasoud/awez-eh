<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
