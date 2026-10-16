<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Stats;

// The namespace keeps this file's helper functions apart from those of other test files

use App\Models\HabitMark;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use App\Services\StatsService;
use App\ValueObjects\DailyCompletionRate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tuesday, 15:00 in Moscow
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
    $this->stats = app(StatsService::class);
});

function completedOn(Task $task, string ...$dates): void
{
    foreach ($dates as $date) {
        TaskCompletion::factory()->for($task)->on($date)->create();
    }
}

// A daily that existed long before the 30-day window
function oldDaily(User $user, int ...$weekdays): Task
{
    $factory = Task::factory()->for($user)->daily();

    return ($weekdays === [] ? $factory : $factory->onWeekdays(...$weekdays))
        ->create(['created_at' => CarbonImmutable::parse('2026-08-01 09:00:00', 'UTC')]);
}

/**
 * @param  list<array{date: string, count: int}>  $heatmap
 */
function countOn(array $heatmap, string $date): int
{
    return collect($heatmap)->firstWhere('date', $date)['count'] ?? -1;
}

it('maps twelve weeks from a Monday to today, empty days included', function () {
    // Act
    $heatmap = $this->stats->heatmap($this->user);

    // Assert: the Monday of this week is 2026-10-12, eleven weeks before it is 2026-07-27
    expect($heatmap)->toHaveCount(79)
        ->and($heatmap[0])->toBe(['date' => '2026-07-27', 'count' => 0])
        ->and(end($heatmap))->toBe(['date' => '2026-10-13', 'count' => 0]);
});

it('counts every completion of a day, deleted tasks too, but not those of others', function () {
    // Arrange
    [$first, $second] = Task::factory()->for($this->user)->count(2)->create();
    completedOn($first, '2026-10-12', '2026-10-13');
    completedOn($second, '2026-10-12');
    $second->delete();
    completedOn(Task::factory()->create(), '2026-10-12');
    completedOn($first, '2026-07-26');

    // Act
    $heatmap = $this->stats->heatmap($this->user);

    // Assert
    expect(countOn($heatmap, '2026-10-12'))->toBe(2)
        ->and(countOn($heatmap, '2026-10-13'))->toBe(1)
        ->and(countOn($heatmap, '2026-07-26'))->toBe(-1)
        ->and(array_sum(array_column($heatmap, 'count')))->toBe(3);
});

it('shares the dailies done over the last thirty finished days', function () {
    // Arrange: done every second day of the window, 2026-09-13 to 2026-10-12
    $daily = oldDaily($this->user);
    $days = array_map(fn (int $n): string => CarbonImmutable::parse('2026-10-12')->subDays($n * 2)->toDateString(), range(0, 14));
    completedOn($daily, ...$days);

    // Act
    $rate = $this->stats->dailyCompletionRate($this->user);

    // Assert
    expect($rate->days)->toBe(30)
        ->and($rate->scheduled)->toBe(30)
        ->and($rate->completed)->toBe(15)
        ->and($rate->percent())->toBe(50);
});

it('counts only the days on the schedule', function () {
    // Arrange: Mondays, Wednesdays and Fridays in the 30 days before today
    oldDaily($this->user, 1, 3, 5);

    // Act
    $rate = $this->stats->dailyCompletionRate($this->user);

    // Assert: from Sunday 2026-09-13 to Monday 2026-10-12 there are 13 such days
    expect($rate->scheduled)->toBe(13)
        ->and($rate->completed)->toBe(0)
        ->and($rate->percent())->toBe(0);
});

it('counts a daily only while it existed', function () {
    // Arrange: created on the evening of 2026-10-07 in Moscow; the other one deleted on 2026-10-03
    Task::factory()->for($this->user)->daily()
        ->create(['created_at' => CarbonImmutable::parse('2026-10-07 20:00:00', 'UTC')]);
    $deleted = oldDaily($this->user);
    $deleted->delete();
    $deleted->forceFill(['deleted_at' => CarbonImmutable::parse('2026-10-03 10:00:00', 'UTC')])->save();

    // Act
    $rate = $this->stats->dailyCompletionRate($this->user);

    // Assert: 23:00 on 2026-10-07 in Moscow, so 2026-10-07..12 is 6 days; 2026-09-13..10-02 is 20 days
    expect($rate->scheduled)->toBe(6 + 20);
});

it('leaves today, todos and other users out', function () {
    // Arrange
    $daily = oldDaily($this->user);
    completedOn($daily, '2026-10-13');
    completedOn(Task::factory()->for($this->user)->todo()->create(['created_at' => '2026-08-01']), '2026-10-12');
    completedOn(oldDaily(User::factory()->create()), '2026-10-12');

    // Act
    $rate = $this->stats->dailyCompletionRate($this->user);

    // Assert
    expect($rate->scheduled)->toBe(30)
        ->and($rate->completed)->toBe(0);
});

it('has no share without dailies', function () {
    // Act
    $rate = $this->stats->dailyCompletionRate($this->user);

    // Assert
    expect($rate->scheduled)->toBe(0)
        ->and($rate->percent())->toBeNull();
});

it('counts habit pluses in the map, but not minuses', function () {
    // Arrange
    $habit = Task::factory()->for($this->user)->habit()->create();
    $daily = oldDaily($this->user);
    completedOn($daily, '2026-10-12');
    HabitMark::factory()->for($habit)->on('2026-10-12')->count(2)->create();
    HabitMark::factory()->for($habit)->on('2026-10-11')->down()->create();

    // Act
    $heatmap = $this->stats->heatmap($this->user);

    // Assert
    expect(countOn($heatmap, '2026-10-12'))->toBe(3)
        ->and(countOn($heatmap, '2026-10-11'))->toBe(0);
});

it('sums the marks of each habit over the last thirty days, today included', function () {
    // Arrange
    $water = Task::factory()->for($this->user)->habit()->create(['title' => 'Пить воду']);
    $feed = Task::factory()->for($this->user)->habit()->create(['title' => 'Листать ленту']);
    HabitMark::factory()->for($water)->on('2026-10-13')->count(2)->create();
    HabitMark::factory()->for($water)->on('2026-09-14')->create();
    HabitMark::factory()->for($water)->on('2026-09-13')->create();
    HabitMark::factory()->for($feed)->on('2026-10-10')->down()->count(3)->create();

    // Act
    $habits = $this->stats->habits($this->user);

    // Assert: 13.09 is the 31st day back, outside the window
    expect($habits)->toBe([
        ['id' => $water->id, 'title' => 'Пить воду', 'up' => 3, 'down' => 0],
        ['id' => $feed->id, 'title' => 'Листать ленту', 'up' => 0, 'down' => 3],
    ]);
});

it('leaves deleted habits, other tasks and other users out of the habit list', function () {
    // Arrange
    $deleted = Task::factory()->for($this->user)->habit()->create();
    HabitMark::factory()->for($deleted)->on('2026-10-13')->create();
    $deleted->delete();
    oldDaily($this->user);
    Task::factory()->habit()->create();

    // Act
    $habits = $this->stats->habits($this->user);

    // Assert
    expect($habits)->toBe([]);
});

it('rounds the share to the nearest percent', function () {
    // Act & Assert
    expect((new DailyCompletionRate(30, 3, 2))->percent())->toBe(67)
        ->and((new DailyCompletionRate(30, 3, 1))->percent())->toBe(33)
        ->and((new DailyCompletionRate(30, 8, 1))->percent())->toBe(13);
});
