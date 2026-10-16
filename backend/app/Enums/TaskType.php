<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskType: string
{
    case Daily = 'daily';
    case Todo = 'todo';
    // Marked plus or minus any number of times a day, never "done"
    case Habit = 'habit';
}
