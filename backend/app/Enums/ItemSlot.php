<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemSlot: string
{
    case Head = 'head';
    case Body = 'body';
    case Legs = 'legs';
    case Weapon = 'weapon';
    case Shield = 'shield';
}
