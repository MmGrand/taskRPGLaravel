<?php

declare(strict_types=1);

namespace App\Enums;

// A plus is the good side of a habit and earns a reward; a minus is only counted
enum HabitDirection: string
{
    case Up = 'up';
    case Down = 'down';
}
