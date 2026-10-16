<?php

declare(strict_types=1);

namespace Tests\Feature\Api\HabitMark;

// The namespace keeps this file's helper functions apart from those of other test files

use App\Enums\HabitDirection;
use App\Enums\TaskDifficulty;
use App\Models\Character;
use App\Models\HabitMark;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 12:00 UTC is 15:00 in Moscow, the same calendar day
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
    $this->character = Character::factory()->for($this->user)->create();
    $this->habit = Task::factory()->for($this->user)->habit()->create();
});

function marksUrl(Task $task): string
{
    return "/api/tasks/{$task->id}/habit-marks";
}

function mark(mixed $test, Task $task, string $direction = 'up'): TestResponse
{
    return $test->actingAs($test->user)->postJson(marksUrl($task), ['direction' => $direction]);
}

it('rewards a plus like a medium completion on the first streak day', function () {
    // Act
    $response = mark($this, $this->habit);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.habit', ['up_today' => 1, 'down_today' => 0])
        ->assertJsonPath('data.rewarded_today', true)
        ->assertJsonPath('reward', ['xp' => 10, 'gold' => 5, 'capped' => false])
        ->assertJsonPath('character.progress.xp_total', 10)
        ->assertJsonPath('character.gold', 5);
    expect(HabitMark::query()->sole())
        ->direction->toBe(HabitDirection::Up)
        ->xp_awarded->toBe(10)
        ->period_date->toDateString()->toBe('2026-10-13');
});

it('scales a plus with the difficulty and the streak bonus', function () {
    // Arrange: two earlier active days make today the third day of the streak
    $hard = Task::factory()->for($this->user)->habit()->difficulty(TaskDifficulty::Hard)->create();
    $daily = Task::factory()->for($this->user)->daily()->create();
    TaskCompletion::factory()->for($daily)->on('2026-10-11')->create();
    TaskCompletion::factory()->for($daily)->on('2026-10-12')->create();

    // Act
    $response = mark($this, $hard);

    // Assert: x2 for hard, +20% for day three
    $response->assertCreated()->assertJsonPath('reward', ['xp' => 24, 'gold' => 12, 'capped' => false]);
});

it('records a minus without touching the hero', function () {
    // Act
    $response = mark($this, $this->habit, 'down');

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.habit', ['up_today' => 0, 'down_today' => 1])
        ->assertJsonPath('data.rewarded_today', false)
        ->assertJsonPath('reward', null)
        ->assertJsonPath('character.gold', 0)
        ->assertJsonPath('character.streak.active_today', false);
});

it('keeps the streak alive with a plus', function () {
    // Arrange
    $daily = Task::factory()->for($this->user)->daily()->create();
    TaskCompletion::factory()->for($daily)->on('2026-10-12')->create();

    // Act
    $response = mark($this, $this->habit);

    // Assert
    $response->assertJsonPath('character.streak', ['days' => 2, 'active_today' => true]);
});

it('stops rewarding a habit after its rewarded pluses of the day', function () {
    // Arrange
    config(['habits.rewarded_per_day' => 2]);
    mark($this, $this->habit)->assertCreated();
    mark($this, $this->habit)->assertCreated();

    // Act
    $response = mark($this, $this->habit);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('reward', ['xp' => 0, 'gold' => 0, 'capped' => true])
        ->assertJsonPath('data.habit.up_today', 3);
    expect($this->character->fresh()->xp)->toBe(20);
});

it('rewards another habit after one reached its own limit', function () {
    // Arrange
    config(['habits.rewarded_per_day' => 1]);
    $other = Task::factory()->for($this->user)->habit()->create();
    mark($this, $this->habit)->assertCreated();

    // Act
    $response = mark($this, $other);

    // Assert
    $response->assertJsonPath('reward.capped', false);
});

it('shares the daily limit with completed tasks', function () {
    // Arrange
    config(['progression.rewards.daily_limit' => 1]);
    $daily = Task::factory()->for($this->user)->daily()->create();
    $this->actingAs($this->user)->putJson("/api/tasks/{$daily->id}/completion")->assertOk();

    // Act
    $response = mark($this, $this->habit);

    // Assert
    $response->assertJsonPath('reward', ['xp' => 0, 'gold' => 0, 'capped' => true]);
});

it('counts a rewarded plus towards the daily limit of completions', function () {
    // Arrange
    config(['progression.rewards.daily_limit' => 1]);
    $daily = Task::factory()->for($this->user)->daily()->create();
    mark($this, $this->habit)->assertCreated();

    // Act
    $response = $this->actingAs($this->user)->putJson("/api/tasks/{$daily->id}/completion");

    // Assert
    $response->assertOk()->assertJsonPath('reward.capped', true);
});

it('refuses a mark on a task that is not a habit', function (string $type) {
    // Arrange
    $task = Task::factory()->for($this->user)->state(['type' => $type])->create();

    // Act
    $response = mark($this, $task);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['task' => 'Это не привычка.']);
    $this->assertDatabaseCount('habit_marks', 0);
})->with(['daily', 'todo']);

it('refuses marks past the daily maximum of a habit', function () {
    // Arrange
    config(['habits.max_marks_per_day' => 2]);
    mark($this, $this->habit)->assertCreated();
    mark($this, $this->habit, 'down')->assertCreated();

    // Act
    $response = mark($this, $this->habit);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['task']);
    $this->assertDatabaseCount('habit_marks', 2);
});

it('validates the direction', function (array $payload) {
    // Act
    $response = $this->actingAs($this->user)->postJson(marksUrl($this->habit), $payload);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['direction']);
})->with([
    'missing' => [[]],
    'unknown' => [['direction' => 'left']],
]);

it('refuses to complete a habit', function () {
    // Act
    $response = $this->actingAs($this->user)->putJson("/api/tasks/{$this->habit->id}/completion");

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['task' => 'Привычку отмечают плюсом или минусом.']);
});

it('undoes the latest mark of today with its reward', function () {
    // Arrange
    mark($this, $this->habit)->assertCreated();
    mark($this, $this->habit, 'down')->assertCreated();
    mark($this, $this->habit)->assertCreated();

    // Act
    $response = $this->actingAs($this->user)->deleteJson(marksUrl($this->habit).'/latest');

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.habit', ['up_today' => 1, 'down_today' => 1])
        ->assertJsonPath('reward', ['xp' => -10, 'gold' => -5, 'capped' => false]);
    expect($this->character->fresh()->xp)->toBe(10);
});

it('undoes a minus without a reward to take back', function () {
    // Arrange
    mark($this, $this->habit, 'down')->assertCreated();

    // Act
    $response = $this->actingAs($this->user)->deleteJson(marksUrl($this->habit).'/latest');

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.habit', ['up_today' => 0, 'down_today' => 0])
        ->assertJsonPath('reward', ['xp' => 0, 'gold' => 0, 'capped' => false]);
});

it('does not undo a mark of an earlier day', function () {
    // Arrange
    HabitMark::factory()->for($this->habit)->on('2026-10-12')->rewarded(10, 5)->create();

    // Act
    $response = $this->actingAs($this->user)->deleteJson(marksUrl($this->habit).'/latest');

    // Assert
    $response->assertOk()->assertJsonPath('reward', null);
    $this->assertDatabaseCount('habit_marks', 1);
});

it('takes back the rewards of today when a habit is deleted', function () {
    // Arrange
    HabitMark::factory()->for($this->habit)->on('2026-10-12')->rewarded(10, 5)->create();
    mark($this, $this->habit)->assertCreated();
    mark($this, $this->habit)->assertCreated();
    mark($this, $this->habit, 'down')->assertCreated();

    // Act
    $response = $this->actingAs($this->user)->deleteJson("/api/tasks/{$this->habit->id}");

    // Assert: the marks of today go, yesterday's plus stays in the history. It made today streak day two,
    // so each plus gave 11 XP and 5 gold
    $response->assertOk()->assertJsonPath('reward', ['xp' => -22, 'gold' => -10, 'capped' => false]);
    $this->assertSoftDeleted($this->habit);
    expect(HabitMark::query()->pluck('period_date')->map->toDateString()->all())->toBe(['2026-10-12']);
});

it('deletes a habit with only minuses today without a reward', function () {
    // Arrange
    mark($this, $this->habit, 'down')->assertCreated();

    // Act
    $response = $this->actingAs($this->user)->deleteJson("/api/tasks/{$this->habit->id}");

    // Assert
    $response->assertOk()->assertJsonPath('reward', null);
});

it('counts a mark for the day of the user timezone', function () {
    // Arrange: 22:30 UTC is already the next day in Moscow
    $this->travelTo(CarbonImmutable::parse('2026-10-13 22:30:00', 'UTC'));

    // Act
    mark($this, $this->habit)->assertCreated();

    // Assert
    expect(HabitMark::query()->sole()->period_date->toDateString())->toBe('2026-10-14');
});

it('marks a habit of a user without a hero without a reward', function () {
    // Arrange
    $this->character->delete();

    // Act
    $response = mark($this, $this->habit);

    // Assert
    $response->assertCreated()->assertJsonPath('reward', null)->assertJsonPath('character', null);
});

it('answers 404 for a habit of another user', function () {
    // Arrange
    $foreign = Task::factory()->habit()->create();

    // Act
    $plus = mark($this, $foreign);
    $undo = $this->actingAs($this->user)->deleteJson(marksUrl($foreign).'/latest');

    // Assert
    $plus->assertNotFound();
    $undo->assertNotFound();
    $this->assertDatabaseCount('habit_marks', 0);
});

it('answers 404 for a habit of another user even with an invalid direction', function () {
    // Arrange: a 422 here would tell that the id exists
    $foreign = Task::factory()->habit()->create();

    // Act
    $response = $this->actingAs($this->user)->postJson(marksUrl($foreign), ['direction' => 'left']);

    // Assert
    $response->assertNotFound();
});

it('requires authentication', function () {
    // Act
    $response = $this->postJson(marksUrl($this->habit), ['direction' => 'up']);

    // Assert
    $response->assertUnauthorized();
});
