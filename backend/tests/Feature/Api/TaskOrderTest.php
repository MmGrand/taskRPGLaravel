<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    [$this->first, $this->second, $this->third] = Task::factory()->for($this->user)->count(3)->create()
        ->each(fn (Task $task, int $index) => $task->forceFill(['position' => $index + 1])->save())
        ->all();
});

it('saves the new order', function () {
    // Act
    $response = $this->actingAs($this->user)->putJson('/api/tasks/order', [
        'ids' => [$this->third->id, $this->first->id, $this->second->id],
    ]);

    // Assert
    $response->assertNoContent();
    $this->actingAs($this->user)->getJson('/api/tasks')
        ->assertJsonPath('data.*.id', [$this->third->id, $this->first->id, $this->second->id]);
});

it('leaves tasks missing from the list in place', function () {
    // Act
    $this->actingAs($this->user)->putJson('/api/tasks/order', ['ids' => [$this->second->id, $this->first->id]])->assertNoContent();

    // Assert
    expect($this->second->fresh()->position)->toBe(1)
        ->and($this->first->fresh()->position)->toBe(2)
        ->and($this->third->fresh()->position)->toBe(3);
});

it('swaps the listed tasks among their own places, so another list never moves', function () {
    // Act: the first and the third swap; the second, say a task of the other list, is not sent
    $this->actingAs($this->user)->putJson('/api/tasks/order', ['ids' => [$this->third->id, $this->first->id]])->assertNoContent();

    // Assert
    expect($this->third->fresh()->position)->toBe(1)
        ->and($this->second->fresh()->position)->toBe(2)
        ->and($this->first->fresh()->position)->toBe(3);
});

it('keeps both moves when the saves of two lists cross', function () {
    // Arrange: a fourth task, so each "list" has two
    $fourth = Task::factory()->for($this->user)->create();

    // Act: one list swaps the first and the third, the other one the second and the fourth
    $this->actingAs($this->user)->putJson('/api/tasks/order', ['ids' => [$this->third->id, $this->first->id]])->assertNoContent();
    $this->actingAs($this->user)->putJson('/api/tasks/order', ['ids' => [$fourth->id, $this->second->id]])->assertNoContent();

    // Assert
    $this->actingAs($this->user)->getJson('/api/tasks')
        ->assertJsonPath('data.*.id', [$this->third->id, $fourth->id, $this->first->id, $this->second->id]);
});

it('saves the order in one statement', function () {
    // Arrange
    DB::enableQueryLog();

    // Act
    $this->actingAs($this->user)->putJson('/api/tasks/order', [
        'ids' => [$this->third->id, $this->second->id, $this->first->id],
    ])->assertNoContent();

    // Assert
    $updates = array_filter(
        array_column(DB::getQueryLog(), 'query'),
        fn (string $sql) => preg_match('/^update "?tasks"?/', $sql) === 1,
    );
    expect($updates)->toHaveCount(1);
});

it('refuses ids that are not tasks of the user', function (Closure $foreignId) {
    // Act
    $response = $this->actingAs($this->user)->putJson('/api/tasks/order', [
        'ids' => [$this->second->id, $foreignId()],
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['ids.1']);
    expect($this->first->fresh()->position)->toBe(1)
        ->and($this->second->fresh()->position)->toBe(2);
})->with([
    'a task of another user' => [fn () => Task::factory()->create()->id],
    'a deleted task' => [fn () => tap(Task::factory()->for(User::query()->firstOrFail())->create())->delete()->id],
    'a missing task' => [fn () => 999_999],
]);

it('refuses an empty or repeated list', function (Closure $ids, string $field) {
    // Act
    $response = $this->actingAs($this->user)->putJson('/api/tasks/order', ['ids' => $ids($this)]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors([$field]);
})->with([
    'empty' => [fn () => [], 'ids'],
    'duplicates' => [fn ($test) => [$test->first->id, $test->first->id], 'ids.0'],
]);

it('requires authentication', function () {
    // Act & Assert
    $this->putJson('/api/tasks/order', ['ids' => [$this->first->id]])->assertUnauthorized();
});
