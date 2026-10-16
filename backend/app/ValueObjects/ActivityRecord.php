<?php

declare(strict_types=1);

namespace App\ValueObjects;

// The whole history of a player in two numbers: days with activity and the longest streak ever
final readonly class ActivityRecord
{
    public function __construct(public int $activeDays, public int $longestStreak) {}
}
