<?php

declare(strict_types=1);

namespace App\ValueObjects;

// How many dailies were due over a number of days and how many of them were done
final readonly class DailyCompletionRate
{
    private const int PERCENT = 100;

    public function __construct(public int $days, public int $scheduled, public int $completed) {}

    // Rounded to the nearest percent; null while nothing was due, since "0%" would blame
    // a player who had no dailies yet
    public function percent(): ?int
    {
        if ($this->scheduled === 0) {
            return null;
        }

        return intdiv($this->completed * self::PERCENT + intdiv($this->scheduled, 2), $this->scheduled);
    }
}
