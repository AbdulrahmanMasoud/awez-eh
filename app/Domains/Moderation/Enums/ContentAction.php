<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Enums;

/**
 * What an owner or a moderator does to moderated content (ContentStatusMachine). The values are
 * the API's `allowed_actions`.
 */
enum ContentAction: string
{
    case Submit = 'submit';
    case Approve = 'approve';
    case Reject = 'reject';
    case TakeDown = 'take_down';
    case Pause = 'pause';
    case Resume = 'resume';
    case MarkSold = 'mark_sold';
    case MarkRented = 'mark_rented';
    case Archive = 'archive';
}
