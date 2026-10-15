<?php

declare(strict_types=1);

namespace Tests\Feature\Api\TaskSchedule;

// The namespace keeps this file's helper functions apart from those of other test files

use App\Models\Character;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use App\Services\StreakService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tuesday, 15:00 in Moscow
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
    $this->character = Character::factory()->for($this->user)->create();
});

function completionUrl(Task $task): string
{
    return "/api/tasks/{$task->id}/completion";
}

it('refuses to complete a daily off its schedule', function () {
    // Arrange
    $gym = Task::factory()->for($this->user)->daily()->onWeekdays(1, 3, 5)->create();

    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($gym));

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('errors.task.0', 'Эта задача сегодня не по расписанию.');
    expect($this->character->fresh()->xp)->toBe(0);
    $this->assertDatabaseCount('task_completions', 0);
});

it('completes a daily on a scheduled day', function () {
    // Arrange
    $daily = Task::factory()->for($this->user)->daily()->onWeekdays(1, 2)->create();

    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($daily));

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.is_completed', true)
        ->assertJsonPath('reward', ['xp' => 10, 'gold' => 5, 'capped' => false]);
});

it('takes the weekday from the calendar of the user', function () {
    // Arrange: 21:30 UTC on Tuesday is already Wednesday in Moscow
    $this->travelTo(CarbonImmutable::parse('2026-10-13 21:30:00', 'UTC'));
    $wednesday = Task::factory()->for($this->user)->daily()->onWeekdays(3)->create();

    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($wednesday));

    // Assert
    $response->assertOk()->assertJsonPath('data.is_completed', true);
});

it('stays idempotent for a task done before its day left the schedule', function () {
    // Arrange
    $daily = Task::factory()->for($this->user)->daily()->create();
    $this->actingAs($this->user)->putJson(completionUrl($daily))->assertOk();
    $this->actingAs($this->user)->patchJson("/api/tasks/{$daily->id}", ['weekdays' => [1]])->assertOk();

    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($daily));

    // Assert
    $response->assertOk()
        ->assertJsonPath('reward', null)
        ->assertJsonPath('data.is_completed', true)
        ->assertJsonPath('data.scheduled_today', false);
});

it('lets such a completion be undone with its reward', function () {
    // Arrange
    $daily = Task::factory()->for($this->user)->daily()->create();
    $this->actingAs($this->user)->putJson(completionUrl($daily))->assertOk();
    $this->actingAs($this->user)->patchJson("/api/tasks/{$daily->id}", ['weekdays' => [1]])->assertOk();

    // Act
    $response = $this->actingAs($this->user)->deleteJson(completionUrl($daily));

    // Assert
    $response->assertOk()->assertJsonPath('reward', ['xp' => -10, 'gold' => -5, 'capped' => false]);
    expect($this->character->fresh()->xp)->toBe(0);
});

it('tells which tasks are due today', function () {
    // Arrange
    $gym = Task::factory()->for($this->user)->daily()->onWeekdays(1, 3, 5)->create();
    $todo = Task::factory()->for($this->user)->todo()->create();

    // Act
    $response = $this->actingAs($this->user)->getJson('/api/tasks');

    // Assert
    $tasks = collect($response->assertOk()->json('data'))->keyBy('id');
    expect($tasks[$gym->id]['scheduled_today'])->toBeFalse()
        ->and($tasks[$gym->id]['weekdays'])->toBe([1, 3, 5])
        ->and($tasks[$todo->id]['scheduled_today'])->toBeTrue();
});

// The author chose so: a day with nothing on the schedule still breaks the streak without a completion
it('keeps the streak rule independent of the schedule', function () {
    // Arrange
    $gym = Task::factory()->for($this->user)->daily()->onWeekdays(1, 3, 5)->create();
    TaskCompletion::factory()->for($gym)->on('2026-10-09')->create();
    TaskCompletion::factory()->for($gym)->on('2026-10-12')->create();

    // Act
    $streak = app(StreakService::class)->current($this->user);

    // Assert: Friday and Monday are apart by the weekend
    expect($streak->days)->toBe(1)
        ->and($streak->activeToday)->toBeFalse();
});
