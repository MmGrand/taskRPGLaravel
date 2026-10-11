<?php

declare(strict_types=1);

namespace App\Enums;

enum HairStyle: string
{
    case Bald = 'bald';
    case Short = 'short';
    case Long = 'long';
    case Braids = 'braids';
    case LongStraight = 'long_straight';
    case Braid = 'braid';
    case Mohawk = 'mohawk';
    case Tousled = 'tousled';
}
