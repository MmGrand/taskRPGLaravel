<?php

declare(strict_types=1);

namespace Tests\Feature\Api\TaskCompletion;

// The namespace keeps this file's helper functions apart from those of other test files

use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
    $this->daily = Task::factory()->for($this->user)->daily()->create();
    $this->todo = Task::factory()->for($this->user)->todo()->create();
});

function completionUrl(Task $task): string
{
    return "/api/tasks/{$task->id}/completion";
}

/**
 * @return array<string, mixed>|null
 */
function listedTask(TestResponse $response, Task $task): ?array
{
    return collect($response->json('data'))->firstWhere('id', $task->id);
}

it('completes a daily for the current day of the user', function () {
    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.is_completed', true)
        ->assertJsonPath('data.completed_at', '2026-10-13T12:00:00+00:00');
    $this->assertDatabaseCount('task_completions', 1);
    expect($this->daily->completions()->sole()->period_date->toDateString())->toBe('2026-10-13');
});

it('ignores a repeated completion', function () {
    // Arrange
    $this->actingAs($this->user)->putJson(completionUrl($this->daily))->assertOk();

    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()->assertJsonPath('data.is_completed', true);
    $this->assertDatabaseCount('task_completions', 1);
});

it('does not duplicate a completion stored by a parallel request', function () {
    // Arrange
    TaskCompletion::factory()->for($this->daily)->on('2026-10-13')->create();

    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()->assertJsonPath('data.is_completed', true);
    $this->assertDatabaseCount('task_completions', 1);
});

it('resets a daily at midnight in the user timezone', function () {
    // Arrange: 23:59 in Moscow
    $this->travelTo(CarbonImmutable::parse('2026-10-13 20:59:00', 'UTC'));
    $this->actingAs($this->user)->putJson(completionUrl($this->daily))->assertOk();

    // Act: 00:00 in Moscow
    $this->travelTo(CarbonImmutable::parse('2026-10-13 21:00:00', 'UTC'));
    $response = $this->actingAs($this->user)->getJson('/api/tasks');

    // Assert
    expect(listedTask($response, $this->daily))
        ->is_completed->toBeFalse()
        ->completed_at->toBeNull();
    $this->assertDatabaseHas('task_completions', ['task_id' => $this->daily->id, 'period_date' => '2026-10-13']);
});

it('decides the day by the user timezone', function () {
    // Arrange
    $utcUser = User::factory()->create(['timezone' => 'UTC']);
    $utcDaily = Task::factory()->for($utcUser)->daily()->create();
    $this->actingAs($this->user)->putJson(completionUrl($this->daily))->assertOk();
    $this->actingAs($utcUser)->putJson(completionUrl($utcDaily))->assertOk();

    // Act: 21:30 UTC is still the 13th in UTC but already the 14th in Moscow
    $this->travelTo(CarbonImmutable::parse('2026-10-13 21:30:00', 'UTC'));
    $moscowList = $this->actingAs($this->user)->getJson('/api/tasks');
    $utcList = $this->actingAs($utcUser)->getJson('/api/tasks');

    // Assert
    expect(listedTask($moscowList, $this->daily)['is_completed'])->toBeFalse()
        ->and(listedTask($utcList, $utcDaily)['is_completed'])->toBeTrue();
});

it('completes a daily again on the next day', function () {
    // Arrange
    $this->actingAs($this->user)->putJson(completionUrl($this->daily))->assertOk();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00', 'UTC'));

    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()->assertJsonPath('data.is_completed', true);
    expect($this->daily->completions()->orderBy('period_date')->pluck('period_date')->map->toDateString()->all())
        ->toBe(['2026-10-13', '2026-10-14']);
});

it('undoes only the completion of the current day', function () {
    // Arrange
    TaskCompletion::factory()->for($this->daily)->on('2026-10-12')->create();
    $this->actingAs($this->user)->putJson(completionUrl($this->daily))->assertOk();

    // Act
    $response = $this->actingAs($this->user)->deleteJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()->assertJsonPath('data.is_completed', false);
    $this->assertDatabaseCount('task_completions', 1);
    $this->assertDatabaseHas('task_completions', ['task_id' => $this->daily->id, 'period_date' => '2026-10-12']);
});

it('treats undoing a not completed task as a no-op', function () {
    // Act
    $response = $this->actingAs($this->user)->deleteJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()->assertJsonPath('data.is_completed', false);
    $this->assertDatabaseCount('task_completions', 0);
});

it('keeps a completed todo listed on its day and drops it the next day', function () {
    // Arrange
    $this->actingAs($this->user)->putJson(completionUrl($this->todo))->assertOk();

    // Act
    $sameDay = $this->actingAs($this->user)->getJson('/api/tasks');
    $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00', 'UTC'));
    $nextDay = $this->actingAs($this->user)->getJson('/api/tasks');

    // Assert
    expect(listedTask($sameDay, $this->todo)['is_completed'])->toBeTrue()
        ->and(listedTask($nextDay, $this->todo))->toBeNull()
        ->and(listedTask($nextDay, $this->daily))->not->toBeNull();
});

it('completes a todo only once', function () {
    // Arrange
    $this->actingAs($this->user)->putJson(completionUrl($this->todo))->assertOk();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00', 'UTC'));

    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($this->todo));

    // Assert
    $response->assertOk()->assertJsonPath('data.is_completed', true);
    $this->assertDatabaseCount('task_completions', 1);
});

it('undoes a todo completion', function () {
    // Arrange
    $this->actingAs($this->user)->putJson(completionUrl($this->todo))->assertOk();

    // Act
    $response = $this->actingAs($this->user)->deleteJson(completionUrl($this->todo));

    // Assert
    $response->assertOk()->assertJsonPath('data.is_completed', false);
    $this->assertDatabaseCount('task_completions', 0);
});

it('does not undo a todo completed on an earlier day', function () {
    // Arrange
    TaskCompletion::factory()->for($this->todo)->on('2026-10-12')->create();

    // Act
    $response = $this->actingAs($this->user)->deleteJson(completionUrl($this->todo));

    // Assert
    $response->assertOk()->assertJsonPath('data.is_completed', true);
    $this->assertDatabaseCount('task_completions', 1);
});

it('hides completion of other users tasks', function () {
    // Arrange
    $foreign = Task::factory()->daily()->create();

    // Act & Assert
    $this->actingAs($this->user)->putJson(completionUrl($foreign))->assertNotFound();
    $this->actingAs($this->user)->deleteJson(completionUrl($foreign))->assertNotFound();
    $this->assertDatabaseCount('task_completions', 0);
});

it('returns 404 for a deleted task', function () {
    // Arrange
    $this->daily->delete();

    // Act & Assert
    $this->actingAs($this->user)->putJson(completionUrl($this->daily))->assertNotFound();
});

it('requires authentication for completion endpoints', function () {
    // Act & Assert
    $this->putJson(completionUrl($this->daily))->assertUnauthorized();
    $this->deleteJson(completionUrl($this->daily))->assertUnauthorized();
});
