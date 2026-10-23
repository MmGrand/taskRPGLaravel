<?php

declare(strict_types=1);

namespace App\Services;

use App\Achievements\AchievementCatalog;
use App\Achievements\AchievementDefinition;
use App\Enums\AchievementMetric;
use App\Enums\HabitDirection;
use App\Models\Character;
use App\Models\Item;
use App\Models\User;
use App\ValueObjects\AchievementMetrics;
use App\ValueObjects\AchievementProgress;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

// Milestones earned once and kept forever: undoing a mark or deleting a task never takes one back
final class AchievementService
{
    public function __construct(
        private readonly AchievementCatalog $catalog,
        private readonly StreakService $streaks,
        private readonly ProgressionService $progression,
    ) {}

    // A few queries whatever the number of achievements. A caller that already holds the hero passes it;
    // otherwise it is read fresh, since the action before may have changed the XP on a locked copy
    public function metrics(User $user, ?Character $character = null): AchievementMetrics
    {
        $character ??= $this->characterOf($user);
        $purchases = $character === null ? 0 : $this->purchases($character);
        $record = $this->streaks->record($user);

        return new AchievementMetrics([
            AchievementMetric::ActiveDays->value => $record->activeDays,
            AchievementMetric::LongestStreak->value => $record->longestStreak,
            AchievementMetric::Level->value => $character === null ? 1 : $this->progression->progressFor($character->xp)->level,
            AchievementMetric::Purchases->value => $purchases,
            AchievementMetric::Catalog->value => $purchases,
            AchievementMetric::HabitDays->value => $this->habitDays($user),
        ], $this->shopItems()->count());
    }

    /**
     * Every achievement in catalog order, earned or not.
     *
     * @return list<AchievementProgress>
     */
    public function progressFor(User $user, ?AchievementMetrics $metrics = null): array
    {
        $metrics ??= $this->metrics($user);
        // The model casts earned_at, so the values are dates already
        /** @var array<string, CarbonImmutable> $earned */
        $earned = $user->achievements()->pluck('earned_at', 'code')->all();
        $definitions = $this->catalog->all();
        $items = Item::query()
            ->whereIn('code', array_filter(array_map(fn (AchievementDefinition $definition) => $definition->rewardItemCode, $definitions)))
            ->get()
            ->keyBy('code');

        return array_map(function (AchievementDefinition $definition) use ($metrics, $earned, $items): AchievementProgress {
            $target = $metrics->targetOf($definition);

            return new AchievementProgress(
                $definition,
                min($metrics->value($definition->metric), $target),
                $target,
                $earned[$definition->code] ?? null,
                $definition->rewardItemCode === null ? null : $items->get($definition->rewardItemCode),
            );
        }, $definitions);
    }

    /**
     * Earns every reached achievement not earned yet and gives its rare item. Idempotent: safe after any action
     * and on read. Parallel calls cannot earn one twice: the unique index lets only one insert through.
     *
     * @return list<AchievementProgress> the achievements this call earned
     */
    public function award(User $user): array
    {
        $character = $this->characterOf($user);

        if ($character === null) {
            return [];
        }

        $metrics = $this->metrics($user, $character);
        $earned = array_flip($user->achievements()->pluck('code')->all());
        $now = now()->toImmutable();
        $new = [];

        foreach ($this->catalog->all() as $definition) {
            if (isset($earned[$definition->code]) || ! $metrics->reached($definition)) {
                continue;
            }

            // Together or not at all: an achievement saved without its item would never give the item later
            $inserted = DB::transaction(function () use ($user, $character, $definition, $now): bool {
                $inserted = DB::table('user_achievements')->insertOrIgnore([
                    'user_id' => $user->id,
                    'code' => $definition->code,
                    'earned_at' => $now,
                ]);

                if ($inserted === 0) {
                    return false;
                }

                $this->giveItem($character, $definition, $now);

                return true;
            });

            if ($inserted) {
                $new[$definition->code] = true;
            }
        }

        if ($new === []) {
            return [];
        }

        return array_values(array_filter(
            $this->progressFor($user, $metrics),
            fn (AchievementProgress $progress): bool => isset($new[$progress->definition->code]),
        ));
    }

    // The unearned achievement closest to done; the first in catalog order on a tie, null once all are earned.
    // One already reached is skipped: the next action or a look at the list earns it
    public function nextGoal(User $user, ?Character $character = null): ?AchievementProgress
    {
        $next = null;

        foreach ($this->progressFor($user, $this->metrics($user, $character)) as $progress) {
            $open = ! $progress->isEarned() && $progress->current < $progress->target;

            if ($open && ($next === null || $progress->ratio() > $next->ratio())) {
                $next = $progress;
            }
        }

        return $next;
    }

    private function characterOf(User $user): ?Character
    {
        return Character::query()->where('user_id', $user->id)->first();
    }

    private function giveItem(Character $character, AchievementDefinition $definition, CarbonImmutable $now): void
    {
        if ($definition->rewardItemCode === null) {
            return;
        }

        $itemId = Item::query()->where('code', $definition->rewardItemCode)->value('id');

        // A catalog seeded without the rare items still lets the achievement be earned
        if ($itemId === null) {
            return;
        }

        DB::table('inventory_items')->insertOrIgnore([
            'character_id' => $character->id,
            'item_id' => $itemId,
            'acquired_at' => $now,
        ]);
    }

    private function purchases(Character $character): int
    {
        return $character->inventoryItems()
            ->whereIn('item_id', $this->shopItems()->select('id'))
            ->count();
    }

    /**
     * What the shop sells: neither the starter kit nor rare items.
     *
     * @return Builder<Item>
     */
    private function shopItems(): Builder
    {
        return Item::query()->where('is_starter', false)->where('is_rare', false);
    }

    // Deleted habits count too: their history is kept
    private function habitDays(User $user): int
    {
        return (int) DB::table('habit_marks')
            ->join('tasks', 'tasks.id', '=', 'habit_marks.task_id')
            ->where('tasks.user_id', $user->id)
            ->where('habit_marks.direction', HabitDirection::Up->value)
            ->distinct()
            ->count('habit_marks.period_date');
    }
}
