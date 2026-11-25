<?php

declare(strict_types=1);

namespace App\Domains\CityGuide\Enums;

enum GuideCategory: string
{
    case Emergency = 'emergency';
    case Hospital = 'hospital';
    case Transport = 'transport';
    case Government = 'government';
    case Utility = 'utility';
    case Education = 'education';
    case Other = 'other';
}
