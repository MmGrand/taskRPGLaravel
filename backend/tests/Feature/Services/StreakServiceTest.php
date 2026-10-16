<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Streak;

// The namespace keeps this file's helper functions apart from those of other test files

use App\Models\HabitMark;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use App\Services\StreakService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
    $this->daily = Task::factory()->for($this->user)->daily()->create();
    $this->streaks = app(StreakService::class);
});

function completedOn(Task $task, string ...$dates): void
{
    foreach ($dates as $date) {
        TaskCompletion::factory()->for($task)->on($date)->create();
    }
}

it('has no streak without completions', function () {
    // Act
    $streak = $this->streaks->current($this->user);

    // Assert
    expect($streak->days)->toBe(0)
        ->and($streak->activeToday)->toBeFalse()
        ->and($this->streaks->lengthIncludingToday($this->user))->toBe(1);
});

it('starts a streak with a completion today', function () {
    // Arrange
    completedOn($this->daily, '2026-10-13');

    // Act
    $streak = $this->streaks->current($this->user);

    // Assert
    expect($streak->days)->toBe(1)
        ->and($streak->activeToday)->toBeTrue();
});

it('counts consecutive days up to today', function () {
    // Arrange
    completedOn($this->daily, '2026-10-11', '2026-10-12', '2026-10-13');

    // Act
    $streak = $this->streaks->current($this->user);

    // Assert
    expect($streak->days)->toBe(3)
        ->and($streak->activeToday)->toBeTrue()
        ->and($this->streaks->lengthIncludingToday($this->user))->toBe(3);
});

it('keeps the streak waiting while today has no completion yet', function () {
    // Arrange
    completedOn($this->daily, '2026-10-11', '2026-10-12');

    // Act
    $streak = $this->streaks->current($this->user);

    // Assert
    expect($streak->days)->toBe(2)
        ->and($streak->activeToday)->toBeFalse()
        ->and($this->streaks->lengthIncludingToday($this->user))->toBe(3);
});

it('breaks the streak after a day without completions', function () {
    // Arrange
    completedOn($this->daily, '2026-10-09', '2026-10-10', '2026-10-11');

    // Act
    $streak = $this->streaks->current($this->user);

    // Assert
    expect($streak->days)->toBe(0)
        ->and($this->streaks->lengthIncludingToday($this->user))->toBe(1);
});

it('counts a day with several completed tasks once', function () {
    // Arrange
    $todo = Task::factory()->for($this->user)->todo()->create();
    completedOn($this->daily, '2026-10-12', '2026-10-13');
    completedOn($todo, '2026-10-13');

    // Act
    $streak = $this->streaks->current($this->user);

    // Assert
    expect($streak->days)->toBe(2);
});

it('counts completed todos and deleted tasks, whose history is kept', function () {
    // Arrange
    $todo = Task::factory()->for($this->user)->todo()->create();
    completedOn($todo, '2026-10-12');
    completedOn($this->daily, '2026-10-13');
    $todo->delete();

    // Act
    $streak = $this->streaks->current($this->user);

    // Assert
    expect($streak->days)->toBe(2);
});

it('ignores completions of other users', function () {
    // Arrange
    completedOn(Task::factory()->daily()->create(), '2026-10-12', '2026-10-13');

    // Act
    $streak = $this->streaks->current($this->user);

    // Assert
    expect($streak->days)->toBe(0);
});

it('moves to the next day at midnight in the user timezone', function () {
    // Arrange: completed on the 13th; 21:00 UTC is already the 14th in Moscow
    completedOn($this->daily, '2026-10-13');
    $this->travelTo(CarbonImmutable::parse('2026-10-13 21:00:00', 'UTC'));

    // Act
    $streak = $this->streaks->current($this->user);

    // Assert
    expect($streak->days)->toBe(1)
        ->and($streak->activeToday)->toBeFalse()
        ->and($this->streaks->lengthIncludingToday($this->user))->toBe(2);
});

it('counts a day with only a habit plus as active', function () {
    // Arrange
    $habit = Task::factory()->for($this->user)->habit()->create();
    completedOn($this->daily, '2026-10-11');
    HabitMark::factory()->for($habit)->on('2026-10-12')->create();
    HabitMark::factory()->for($habit)->on('2026-10-12')->create();

    // Act
    $streak = $this->streaks->current($this->user);

    // Assert
    expect($streak->days)->toBe(2)
        ->and($this->streaks->activeDayCount($this->user))->toBe(2);
});

it('does not count a day with only a habit minus', function () {
    // Arrange
    $habit = Task::factory()->for($this->user)->habit()->create();
    completedOn($this->daily, '2026-10-11');
    HabitMark::factory()->for($habit)->on('2026-10-12')->down()->create();

    // Act
    $streak = $this->streaks->current($this->user);

    // Assert
    expect($streak->days)->toBe(0)
        ->and($this->streaks->activeDayCount($this->user))->toBe(1);
});

it('finds the longest streak even after it broke', function () {
    // Arrange: five days, a gap, three days, a gap, then yesterday and today
    completedOn($this->daily, '2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-09-05');
    completedOn($this->daily, '2026-09-10', '2026-09-11', '2026-09-12');
    completedOn($this->daily, '2026-10-12', '2026-10-13');

    // Act
    $longest = $this->streaks->longest($this->user);

    // Assert
    expect($longest)->toBe(5)
        ->and($this->streaks->current($this->user)->days)->toBe(2);
});

it('takes the current run as the longest when it is', function () {
    // Arrange
    completedOn($this->daily, '2026-09-01', '2026-10-11', '2026-10-12', '2026-10-13');

    // Act
    $longest = $this->streaks->longest($this->user);

    // Assert
    expect($longest)->toBe(3);
});

it('has no longest streak without activity', function () {
    // Act
    $longest = $this->streaks->longest($this->user);

    // Assert
    expect($longest)->toBe(0)
        ->and($this->streaks->activeDayCount($this->user))->toBe(0);
});

it('counts the days of deleted tasks and habits too', function () {
    // Arrange
    $habit = Task::factory()->for($this->user)->habit()->create();
    HabitMark::factory()->for($habit)->on('2026-10-12')->create();
    completedOn($this->daily, '2026-10-13');
    $habit->delete();
    $this->daily->delete();

    // Act
    $count = $this->streaks->activeDayCount($this->user);

    // Assert
    expect($count)->toBe(2);
});
