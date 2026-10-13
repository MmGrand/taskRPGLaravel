<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class Streak
{
    public function __construct(public int $days, public bool $activeToday) {}
}
