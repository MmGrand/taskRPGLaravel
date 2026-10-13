<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskType: string
{
    case Daily = 'daily';
    case Todo = 'todo';
}
