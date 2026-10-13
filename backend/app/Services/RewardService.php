<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\ValueObjects\Reward;

final class RewardService
{
    private const int PERCENT = 100;

    // Streak day 1 earns the base reward; each later day adds a capped bonus, rounded down
    public function rewardFor(int $streakDays): Reward
    {
        $bonus = min(
            max(0, $streakDays - 1) * config()->integer('progression.streak_bonus.percent_per_day'),
            config()->integer('progression.streak_bonus.max_percent'),
        );

        return new Reward(
            intdiv(config()->integer('progression.rewards.xp') * (self::PERCENT + $bonus), self::PERCENT),
            intdiv(config()->integer('progression.rewards.gold') * (self::PERCENT + $bonus), self::PERCENT),
        );
    }

    // The caller holds the row lock of the character
    public function grant(Character $character, Reward $reward): void
    {
        $character->xp += $reward->xp;
        $character->gold += $reward->gold;
        $character->save();
    }

    // Takes back at most what the character has, so gold spent meanwhile never goes negative.
    // The caller holds the row lock of the character
    public function revoke(Character $character, Reward $reward): Reward
    {
        $taken = new Reward(min($reward->xp, $character->xp), min($reward->gold, $character->gold));
        $character->xp -= $taken->xp;
        $character->gold -= $taken->gold;
        $character->save();

        return $taken;
    }
}
