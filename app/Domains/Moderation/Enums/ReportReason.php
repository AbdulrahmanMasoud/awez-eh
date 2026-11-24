<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Enums;

enum ReportReason: string
{
    case Fake = 'fake';
    case Scam = 'scam';
    case Spam = 'spam';
    case WrongPrice = 'wrong_price';
    case Offensive = 'offensive';
    case Other = 'other';
}
