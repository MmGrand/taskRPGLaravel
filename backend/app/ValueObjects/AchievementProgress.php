<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Achievements\AchievementDefinition;
use App\Models\Item;
use Carbon\CarbonImmutable;

// One achievement as a player sees it: how far along, and when it was earned
final readonly class AchievementProgress
{
    // current never goes past target, so a finished bar reads "7 / 7", not "12 / 7"
    public function __construct(
        public AchievementDefinition $definition,
        public int $current,
        public int $target,
        public ?CarbonImmutable $earnedAt,
        public ?Item $rewardItem,
    ) {}

    public function isEarned(): bool
    {
        return $this->earnedAt !== null;
    }

    // Share of the way done, 0 to 1; an achievement with nothing to reach counts as not started
    public function ratio(): float
    {
        return $this->target > 0 ? $this->current / $this->target : 0.0;
    }
}
