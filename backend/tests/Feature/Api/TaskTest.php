<?php

declare(strict_types=1);

use App\Enums\TaskType;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('creates a task of either type', function (string $type) {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/tasks', ['title' => '30 минут Go', 'type' => $type]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.title', '30 минут Go')
        ->assertJsonPath('data.type', $type)
        ->assertJsonPath('data.is_completed', false)
        ->assertJsonPath('data.completed_at', null);
    $this->assertDatabaseHas('tasks', ['user_id' => $this->user->id, 'title' => '30 минут Go', 'type' => $type]);
})->with(['daily', 'todo']);

it('accepts emoji in the title', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/tasks', ['title' => 'Зарядка 💪', 'type' => 'daily']);

    // Assert
    $response->assertCreated()->assertJsonPath('data.title', 'Зарядка 💪');
});

it('rejects invalid task data', function (array $payload, string $field) {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/tasks', $payload);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors([$field]);
    $this->assertDatabaseCount('tasks', 0);
})->with([
    'empty title' => [['title' => '', 'type' => 'daily'], 'title'],
    'blank title' => [['title' => '   ', 'type' => 'daily'], 'title'],
    'too long title' => [['title' => str_repeat('a', 121), 'type' => 'daily'], 'title'],
    'unknown type' => [['title' => 'Зарядка', 'type' => 'habit'], 'type'],
    'missing type' => [['title' => 'Зарядка'], 'type'],
]);

it('returns validation messages in Russian', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/tasks', ['type' => 'daily']);

    // Assert
    $response->assertJsonValidationErrors(['title' => 'Поле название обязательно.']);
});

it('refuses to create tasks over the limit', function () {
    // Arrange
    config(['tasks.max_active_per_user' => 2]);
    Task::factory()->for($this->user)->count(2)->create();

    // Act
    $response = $this->actingAs($this->user)->postJson('/api/tasks', ['title' => 'Третья', 'type' => 'todo']);

    // Assert
    $response->assertUnprocessable()->assertJsonPath('message', 'Task limit reached.');
    $this->assertDatabaseCount('tasks', 2);
});

// A real race cannot be played in one process, so this keeps the order that prevents it: lock, then count
it('locks the user row before counting tasks', function () {
    // Arrange
    DB::enableQueryLog();

    // Act
    $this->actingAs($this->user)->postJson('/api/tasks', ['title' => 'Первая', 'type' => 'todo'])->assertCreated();

    // Assert
    $queries = array_column(DB::getQueryLog(), 'query');
    $lock = array_find_key($queries, fn (string $sql) => str_contains($sql, 'from "users"') && str_contains($sql, 'for update'));
    $count = array_find_key($queries, fn (string $sql) => str_contains($sql, 'count(*)') && str_contains($sql, 'from "tasks"'));
    expect($lock)->not->toBeNull()
        ->and($count)->not->toBeNull()
        ->and($lock)->toBeLessThan($count);
});

// Such todos drop off the list and cannot be deleted from it, so they must not block new tasks
it('does not count todos finished on an earlier day towards the limit', function () {
    // Arrange
    config(['tasks.max_active_per_user' => 1]);
    $done = Task::factory()->for($this->user)->todo()->create();
    TaskCompletion::factory()->for($done)->on('2020-01-01')->create();

    // Act
    $response = $this->actingAs($this->user)->postJson('/api/tasks', ['title' => 'Новая', 'type' => 'todo']);

    // Assert
    $response->assertCreated();
});

it('does not count deleted tasks towards the limit', function () {
    // Arrange
    config(['tasks.max_active_per_user' => 2]);
    [$first] = Task::factory()->for($this->user)->count(2)->create();
    $first->delete();

    // Act
    $response = $this->actingAs($this->user)->postJson('/api/tasks', ['title' => 'Третья', 'type' => 'todo']);

    // Assert
    $response->assertCreated();
});

it('lists only own not deleted tasks in creation order', function () {
    // Arrange
    $first = Task::factory()->for($this->user)->daily()->create();
    $second = Task::factory()->for($this->user)->todo()->create();
    Task::factory()->for($this->user)->create()->delete();
    Task::factory()->create();

    // Act
    $response = $this->actingAs($this->user)->getJson('/api/tasks');

    // Assert
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.1.id', $second->id);
});

it('lists tasks with a constant number of queries', function () {
    // Arrange
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->user)->getJson('/api/tasks')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    TaskCompletion::factory()->for(Task::factory()->for($this->user)->daily())->create();
    $withOneTask = $countQueries();

    // Act
    Task::factory()->for($this->user)->daily()->count(4)->create()->each(
        fn (Task $task) => TaskCompletion::factory()->for($task)->create(),
    );
    $withFiveTasks = $countQueries();

    // Assert
    expect($withFiveTasks)->toBe($withOneTask);
});

it('renames a task but keeps its type', function () {
    // Arrange
    $task = Task::factory()->for($this->user)->daily()->create();

    // Act
    $response = $this->actingAs($this->user)->patchJson("/api/tasks/{$task->id}", ['title' => 'Новое', 'type' => 'todo']);

    // Assert
    $response->assertOk()->assertJsonPath('data.title', 'Новое')->assertJsonPath('data.type', 'daily');
    expect($task->fresh()->type)->toBe(TaskType::Daily);
});

it('rejects an empty title on rename', function () {
    // Arrange
    $task = Task::factory()->for($this->user)->create(['title' => 'Старое']);

    // Act
    $response = $this->actingAs($this->user)->patchJson("/api/tasks/{$task->id}", ['title' => '']);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['title']);
    expect($task->fresh()->title)->toBe('Старое');
});

it('soft deletes a task', function () {
    // Arrange
    $task = Task::factory()->for($this->user)->create();

    // Act
    $response = $this->actingAs($this->user)->deleteJson("/api/tasks/{$task->id}");

    // Assert: the user of this file has no hero, so there is nobody to take a reward from
    $response->assertOk()
        ->assertJsonPath('data.id', $task->id)
        ->assertJsonPath('character', null)
        ->assertJsonPath('reward', null);
    $this->assertSoftDeleted($task);
    $this->actingAs($this->user)->deleteJson("/api/tasks/{$task->id}")->assertNotFound();
});

it('hides tasks of other users', function () {
    // Arrange
    $foreign = Task::factory()->create(['title' => 'Чужая']);

    // Act & Assert
    $this->actingAs($this->user)->patchJson("/api/tasks/{$foreign->id}", ['title' => 'Моя'])->assertNotFound();
    $this->actingAs($this->user)->deleteJson("/api/tasks/{$foreign->id}")->assertNotFound();
    expect($foreign->fresh())->title->toBe('Чужая')->deleted_at->toBeNull();
});

it('requires authentication for task endpoints', function () {
    // Arrange
    $task = Task::factory()->create();

    // Act & Assert
    $this->getJson('/api/tasks')->assertUnauthorized();
    $this->postJson('/api/tasks', ['title' => 'Зарядка', 'type' => 'daily'])->assertUnauthorized();
    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Зарядка'])->assertUnauthorized();
    $this->deleteJson("/api/tasks/{$task->id}")->assertUnauthorized();
});
