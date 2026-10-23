<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Achievement;

// The namespace keeps this file's helper functions apart from those of other test files

use App\Achievements\AchievementCatalog;
use App\Models\Character;
use App\Models\HabitMark;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\StarterKit;
use App\ValueObjects\AchievementProgress;
use Carbon\CarbonImmutable;
use Database\Seeders\ItemCatalogSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->seed(ItemCatalogSeeder::class);
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
    $this->character = Character::factory()->for($this->user)->create();
    app(StarterKit::class)->grantTo($this->character);
    $this->daily = Task::factory()->for($this->user)->daily()->create();
    $this->achievements = app(AchievementService::class);
});

// Completions on the given number of days in a row, ending on $last
function activeDays(Task $task, int $days, string $last = '2026-10-13'): void
{
    $end = CarbonImmutable::parse($last);

    for ($day = 0; $day < $days; $day++) {
        TaskCompletion::factory()->for($task)->on($end->subDays($day)->toDateString())->create();
    }
}

/**
 * @param  list<AchievementProgress>  $progress
 * @return list<string>
 */
function codes(array $progress): array
{
    return array_map(fn (AchievementProgress $entry): string => $entry->definition->code, $progress);
}

/**
 * @param  list<AchievementProgress>  $progress
 */
function progressOf(array $progress, string $code): AchievementProgress
{
    return collect($progress)->first(fn (AchievementProgress $entry) => $entry->definition->code === $code);
}

it('earns nothing without activity', function () {
    // Act
    $earned = $this->achievements->award($this->user);

    // Assert
    expect($earned)->toBe([])
        ->and($this->user->achievements()->count())->toBe(0);
});

it('awards the first step after the first completion', function () {
    // Arrange
    activeDays($this->daily, 1);

    // Act
    $earned = $this->achievements->award($this->user);

    // Assert
    expect(codes($earned))->toBe(['first_step'])
        ->and($earned[0]->earnedAt?->toIso8601String())->toBe('2026-10-13T12:00:00+00:00');
});

it('awards a week of streak with its rare item', function () {
    // Arrange
    activeDays($this->daily, 7);

    // Act
    $earned = $this->achievements->award($this->user);

    // Assert
    expect(codes($earned))->toBe(['first_step', 'streak_3', 'streak_7'])
        ->and(progressOf($earned, 'streak_7')->rewardItem?->code)->toBe('paladin_pants')
        ->and($this->character->inventoryItems()->whereRelation('item', 'code', 'paladin_pants')->exists())->toBeTrue();
});

it('awards each achievement once under repeated calls', function () {
    // Arrange
    activeDays($this->daily, 7);
    $this->achievements->award($this->user);

    // Act
    $again = $this->achievements->award($this->user);

    // Assert
    expect($again)->toBe([])
        ->and($this->user->achievements()->count())->toBe(3)
        ->and($this->character->inventoryItems()->whereRelation('item', 'code', 'paladin_pants')->count())->toBe(1);
});

it('keeps an achievement unearned when giving its rare item fails, so a later call gives both', function () {
    // Arrange
    activeDays($this->daily, 7);
    $failItemInsert = true;
    DB::listen(function (QueryExecuted $query) use (&$failItemInsert): void {
        if ($failItemInsert && str_contains($query->sql, 'insert') && str_contains($query->sql, 'inventory_items')) {
            throw new RuntimeException('inventory is unavailable');
        }
    });

    // Act
    $failed = rescue(fn () => $this->achievements->award($this->user), fn () => null, report: false);
    $failItemInsert = false;
    $retried = $this->achievements->award($this->user);

    // Assert: the achievements without an item went through the first time, the failed one waited
    expect($failed)->toBeNull()
        ->and(codes($retried))->toBe(['streak_7'])
        ->and($this->character->inventoryItems()->whereRelation('item', 'code', 'paladin_pants')->exists())->toBeTrue();
});

it('reports only the newly earned achievements', function () {
    // Arrange
    activeDays($this->daily, 2, '2026-10-12');
    $this->achievements->award($this->user);
    TaskCompletion::factory()->for($this->daily)->on('2026-10-13')->create();

    // Act
    $earned = $this->achievements->award($this->user);

    // Assert
    expect(codes($earned))->toBe(['streak_3']);
});

it('counts the longest streak even after it broke', function () {
    // Arrange: a week long ago, only today now
    activeDays($this->daily, 7, '2026-09-10');
    activeDays($this->daily, 1);

    // Act
    $earned = $this->achievements->award($this->user);

    // Assert
    expect(codes($earned))->toContain('streak_7');
});

it('keeps an achievement after the completion that earned it is undone', function () {
    // Arrange
    activeDays($this->daily, 1);
    $this->achievements->award($this->user);

    // Act
    $this->daily->completions()->delete();

    // Assert
    expect(progressOf($this->achievements->progressFor($this->user), 'first_step')->isEarned())->toBeTrue();
});

it('awards levels from the XP of the hero', function () {
    // Arrange: level 5 on the reference balance needs 50 + 141 + 260 + 400 XP
    Character::query()->whereKey($this->character->id)->update(['xp' => 851]);

    // Act
    $earned = $this->achievements->award($this->user);

    // Assert
    expect(codes($earned))->toBe(['level_5']);
});

it('ignores the starter kit and rare items as purchases', function () {
    // Arrange
    InventoryItem::factory()->for($this->character)->for(Item::query()->where('code', 'frost_axe')->sole())->create();

    // Act
    $progress = $this->achievements->progressFor($this->user);

    // Assert
    expect(progressOf($progress, 'first_purchase')->current)->toBe(0)
        ->and(codes($this->achievements->award($this->user)))->not->toContain('first_purchase');
});

it('awards the first purchase and the collector for the whole shop', function () {
    // Arrange
    Item::query()->where('is_starter', false)->where('is_rare', false)->each(
        fn (Item $item) => InventoryItem::factory()->for($this->character)->for($item)->create(),
    );

    // Act
    $earned = $this->achievements->award($this->user);

    // Assert
    expect(codes($earned))->toBe(['first_purchase', 'collector'])
        ->and(progressOf($earned, 'collector')->target)->toBe(27);
});

it('never awards the collector while the shop is empty', function () {
    // Arrange
    Item::query()->where('is_starter', false)->where('is_rare', false)->delete();

    // Act
    $earned = $this->achievements->award($this->user);

    // Assert
    expect(codes($earned))->not->toContain('collector');
});

it('counts habit days only for pluses', function () {
    // Arrange
    $habit = Task::factory()->for($this->user)->habit()->create();
    foreach (['2026-10-07', '2026-10-08', '2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12'] as $date) {
        HabitMark::factory()->for($habit)->on($date)->count(2)->create();
    }
    HabitMark::factory()->for($habit)->on('2026-10-13')->down()->create();

    // Act
    $progress = $this->achievements->progressFor($this->user);

    // Assert
    expect(progressOf($progress, 'habit_7')->current)->toBe(6)
        ->and(codes($this->achievements->award($this->user)))->not->toContain('habit_7');
});

it('caps the progress at the target', function () {
    // Arrange
    activeDays($this->daily, 12);

    // Act
    $progress = $this->achievements->progressFor($this->user);

    // Assert
    expect(progressOf($progress, 'streak_7'))->current->toBe(7)->target->toBe(7)
        ->and(progressOf($progress, 'streak_30'))->current->toBe(12)->target->toBe(30);
});

it('picks the unearned goal closest to done', function () {
    // Arrange: 9 of 10 active days beats 1 of 5 levels and 9 of 30 streak days
    activeDays($this->daily, 9);
    $this->achievements->award($this->user);

    // Act
    $goal = $this->achievements->nextGoal($this->user);

    // Assert
    expect($goal?->definition->code)->toBe('active_10')
        ->and($goal?->current)->toBe(9);
});

it('points a new player to level 5, a fifth of the way there from level 1', function () {
    // Act: nothing done yet, every other goal is at zero
    $goal = $this->achievements->nextGoal($this->user);

    // Assert
    expect($goal?->definition->code)->toBe('level_5')
        ->and($goal?->current)->toBe(1);
});

it('has no next goal once everything is earned', function () {
    // Arrange
    foreach (app(AchievementCatalog::class)->all() as $definition) {
        $this->user->achievements()->create(['code' => $definition->code, 'earned_at' => now()]);
    }

    // Act
    $goal = $this->achievements->nextGoal($this->user);

    // Assert
    expect($goal)->toBeNull();
});

it('gives nothing to a user without a hero', function () {
    // Arrange
    activeDays($this->daily, 7);
    $this->character->delete();

    // Act
    $earned = $this->achievements->award($this->user);

    // Assert
    expect($earned)->toBe([])
        ->and($this->user->achievements()->count())->toBe(0);
});
