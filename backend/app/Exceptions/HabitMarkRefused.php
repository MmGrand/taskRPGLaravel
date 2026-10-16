<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

// The player may not mark this task right now; the message is meant for the player
final class HabitMarkRefused extends DomainException
{
    public static function notHabit(): self
    {
        return new self(__('tasks.not_habit'));
    }

    public static function tooMany(): self
    {
        return new self(__('tasks.too_many_marks'));
    }
}
