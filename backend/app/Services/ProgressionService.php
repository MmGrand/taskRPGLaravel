<?php

declare(strict_types=1);

namespace App\Services;

use App\ValueObjects\Progress;

final class ProgressionService
{
    // XP needed to go from this level to the next one; at least 1, so the level loop always ends
    public function xpToNextLevel(int $level): int
    {
        $base = config()->integer('progression.levels.base_xp');
        $exponent = config()->float('progression.levels.exponent');

        return max(1, (int) round($base * $level ** $exponent));
    }

    // The level is never stored: it follows from the total, so taking XP back can lower it
    public function progressFor(int $xpTotal): Progress
    {
        $level = 1;
        $remaining = max(0, $xpTotal);

        while ($remaining >= $this->xpToNextLevel($level)) {
            $remaining -= $this->xpToNextLevel($level);
            $level++;
        }

        return new Progress($level, $xpTotal, $remaining, $this->xpToNextLevel($level));
    }
}
