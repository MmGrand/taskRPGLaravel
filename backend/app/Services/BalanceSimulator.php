<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskDifficulty;
use App\Models\Item;
use App\ValueObjects\Simulation;
use App\ValueObjects\SimulationEvent;
use Illuminate\Database\Eloquent\Collection;

// Plays the game on the real formulas, so the balance can be tuned by numbers instead of by feel
final class BalanceSimulator
{
    public function __construct(
        private readonly RewardService $rewards,
        private readonly ProgressionService $progression,
    ) {}

    /**
     * A player who completes $tasksPerDay tasks every day: the streak never breaks, the daily limit applies,
     * and gold goes to the cheapest unlocked item as soon as it is affordable. Starter items are owned already.
     *
     * @param  Collection<int, Item>  $catalog
     */
    public function simulate(int $tasksPerDay, int $days, TaskDifficulty $difficulty, Collection $catalog): Simulation
    {
        $rewarded = min($tasksPerDay, config()->integer('progression.rewards.daily_limit'));
        // Starter items are owned already and rare ones are never sold
        $toBuy = $catalog->reject(fn (Item $item): bool => $item->is_starter || $item->is_rare)
            ->sortBy([['price', 'asc'], ['required_level', 'asc'], ['id', 'asc']])
            ->values();
        $xp = 0;
        $gold = 0;
        $level = 1;
        $events = [];
        $levelDays = [];
        $catalogDay = null;

        for ($day = 1; $day <= $days; $day++) {
            $reward = $this->rewards->rewardFor($day, $difficulty);
            $xp += $reward->xp * $rewarded;
            $gold += $reward->gold * $rewarded;

            // A big day may climb several levels; each one is reported with what it unlocks
            $reached = $this->progression->progressFor($xp)->level;
            for ($next = $level + 1; $next <= $reached; $next++) {
                $levelDays[$next] = $day;
                $events[] = new SimulationEvent($day, SimulationEvent::LEVEL, $this->levelText($next, $catalog));
            }
            $level = $reached;

            // The list is sorted by price, so the first affordable unlocked item is the cheapest one
            while (($item = $toBuy->first(fn (Item $item): bool => $item->required_level <= $level && $item->price <= $gold)) !== null) {
                $gold -= $item->price;
                $toBuy = $toBuy->reject(fn (Item $other): bool => $other->is($item))->values();
                $events[] = new SimulationEvent($day, SimulationEvent::PURCHASE, "Bought {$item->name} ({$item->price}), gold left {$gold}");
            }

            if ($toBuy->isEmpty() && $catalogDay === null && $catalog->isNotEmpty()) {
                $catalogDay = $day;
            }
        }

        return new Simulation($events, $levelDays, $catalogDay, $level);
    }

    /**
     * @param  Collection<int, Item>  $catalog
     */
    private function levelText(int $level, Collection $catalog): string
    {
        $unlocked = $catalog
            ->filter(fn (Item $item): bool => $item->required_level === $level && ! $item->is_starter)
            ->pluck('name')
            ->implode(', ');

        return $unlocked === '' ? "Level {$level}" : "Level {$level}, unlocked: {$unlocked}";
    }
}
