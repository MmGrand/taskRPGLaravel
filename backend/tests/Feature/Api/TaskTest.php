<?php

declare(strict_types=1);

use App\Enums\TaskDifficulty;
use App\Enums\TaskType;
use App\Models\HabitMark;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use App\ValueObjects\Weekdays;
use Carbon\CarbonImmutable;
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
})->with(['daily', 'todo', 'habit']);

it('creates a habit with nothing marked today', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/tasks', ['title' => 'Пить воду', 'type' => 'habit']);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.habit', ['up_today' => 0, 'down_today' => 0])
        ->assertJsonPath('data.scheduled_today', true)
        ->assertJsonPath('data.rewarded_today', false);
});

it('leaves the habit counts out of a daily and a todo', function (string $type) {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/tasks', ['title' => 'Зарядка', 'type' => $type]);

    // Assert
    $response->assertCreated()->assertJsonPath('data.habit', null);
})->with(['daily', 'todo']);

it('lists a habit with the marks of today only', function () {
    // Arrange
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $habit = Task::factory()->for($this->user)->habit()->create();
    HabitMark::factory()->for($habit)->on('2026-10-13')->count(2)->rewarded(5, 4)->create();
    HabitMark::factory()->for($habit)->on('2026-10-13')->down()->create();
    HabitMark::factory()->for($habit)->on('2026-10-12')->count(3)->create();

    // Act
    $response = $this->actingAs($this->user)->getJson('/api/tasks');

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.0.habit', ['up_today' => 2, 'down_today' => 1])
        ->assertJsonPath('data.0.is_completed', false)
        ->assertJsonPath('data.0.rewarded_today', true);
});

it('counts a habit towards the task limit', function () {
    // Arrange
    config(['tasks.max_active_per_user' => 1]);
    Task::factory()->for($this->user)->habit()->create();

    // Act
    $response = $this->actingAs($this->user)->postJson('/api/tasks', ['title' => 'Ещё одна', 'type' => 'daily']);

    // Assert
    $response->assertUnprocessable();
});

it('refuses weekdays for a habit on update', function () {
    // Arrange
    $habit = Task::factory()->for($this->user)->habit()->create();

    // Act
    $response = $this->actingAs($this->user)->patchJson("/api/tasks/{$habit->id}", ['weekdays' => [1]]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['weekdays']);
});

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
    'unknown type' => [['title' => 'Зарядка', 'type' => 'weekly'], 'type'],
    'missing type' => [['title' => 'Зарядка'], 'type'],
    'unknown difficulty' => [['title' => 'Зарядка', 'type' => 'daily', 'difficulty' => 'epic'], 'difficulty'],
    'weekdays for a todo' => [['title' => 'Зарядка', 'type' => 'todo', 'weekdays' => [1]], 'weekdays'],
    'weekdays for a habit' => [['title' => 'Пить воду', 'type' => 'habit', 'weekdays' => [1]], 'weekdays'],
    'empty weekdays' => [['title' => 'Зарядка', 'type' => 'daily', 'weekdays' => []], 'weekdays'],
    'weekday past sunday' => [['title' => 'Зарядка', 'type' => 'daily', 'weekdays' => [8]], 'weekdays.0'],
    'weekday as a name' => [['title' => 'Зарядка', 'type' => 'daily', 'weekdays' => ['пн']], 'weekdays.0'],
    'duplicate weekdays' => [['title' => 'Зарядка', 'type' => 'daily', 'weekdays' => [1, 1]], 'weekdays.0'],
]);

it('creates a daily with a difficulty and weekdays', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/tasks', [
        'title' => 'Спортзал',
        'type' => 'daily',
        'difficulty' => 'hard',
        'weekdays' => [5, 1, 3],
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.difficulty', 'hard')
        ->assertJsonPath('data.weekdays', [1, 3, 5]);
    $this->assertDatabaseHas('tasks', ['title' => 'Спортзал', 'difficulty' => 'hard', 'weekdays' => 21]);
});

it('makes a new task medium and due every day', function (string $type) {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/tasks', ['title' => 'Зарядка', 'type' => $type]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.difficulty', 'medium')
        ->assertJsonPath('data.weekdays', [1, 2, 3, 4, 5, 6, 7])
        ->assertJsonPath('data.scheduled_today', true);
})->with(['daily', 'todo']);

it('puts a new task last', function () {
    // Arrange
    $first = Task::factory()->for($this->user)->create();
    $first->forceFill(['position' => 7])->save();

    // Act
    $this->actingAs($this->user)->postJson('/api/tasks', ['title' => 'Последняя', 'type' => 'todo'])->assertCreated();

    // Assert
    expect(Task::query()->where('title', 'Последняя')->sole()->position)->toBe(8);
});

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

it('lists tasks in their saved order', function () {
    // Arrange
    $first = Task::factory()->for($this->user)->create();
    $second = Task::factory()->for($this->user)->create();
    $first->forceFill(['position' => 2])->save();
    $second->forceFill(['position' => 1])->save();

    // Act
    $response = $this->actingAs($this->user)->getJson('/api/tasks');

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.1.id', $first->id);
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

it('changes the difficulty and weekdays and keeps the title', function () {
    // Arrange
    $task = Task::factory()->for($this->user)->daily()->create(['title' => 'Спортзал']);

    // Act
    $response = $this->actingAs($this->user)->patchJson("/api/tasks/{$task->id}", ['difficulty' => 'hard', 'weekdays' => [2, 4]]);

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.title', 'Спортзал')
        ->assertJsonPath('data.difficulty', 'hard')
        ->assertJsonPath('data.weekdays', [2, 4]);
    expect($task->fresh())
        ->difficulty->toBe(TaskDifficulty::Hard)
        ->weekdays->isoDays()->toBe([2, 4]);
});

// The rules read the type of the task, so ownership comes first: a 422 would tell a todo from a daily
it('does not tell the type of a task of another user', function (string $type) {
    // Arrange
    $foreign = Task::factory()->create(['type' => $type]);

    // Act
    $response = $this->actingAs($this->user)->patchJson("/api/tasks/{$foreign->id}", ['weekdays' => [1]]);

    // Assert
    $response->assertNotFound();
})->with(['daily', 'todo']);

it('gives every new task the next place, whatever creates it', function () {
    // Act
    [$first, $second] = Task::factory()->for($this->user)->count(2)->create();
    $other = Task::factory()->create();

    // Assert
    expect($second->position)->toBe($first->position + 1)
        ->and($other->position)->toBe(1);
});

it('refuses weekdays for a todo on update', function () {
    // Arrange
    $task = Task::factory()->for($this->user)->todo()->create();

    // Act
    $response = $this->actingAs($this->user)->patchJson("/api/tasks/{$task->id}", ['weekdays' => [1]]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['weekdays']);
    expect($task->fresh()->weekdays->mask)->toBe(Weekdays::EVERY_DAY_MASK);
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
