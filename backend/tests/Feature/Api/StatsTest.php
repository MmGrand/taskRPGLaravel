<?php

declare(strict_types=1);

use App\Models\HabitMark;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
});

it('answers with the completion map and the share of dailies', function () {
    // Arrange
    $daily = Task::factory()->for($this->user)->daily()->create(['created_at' => '2026-08-01']);
    TaskCompletion::factory()->for($daily)->on('2026-10-12')->create();

    // Act
    $response = $this->actingAs($this->user)->getJson('/api/stats');

    // Assert
    $response->assertOk()
        ->assertJsonCount(79, 'data.heatmap')
        ->assertJsonPath('data.heatmap.77', ['date' => '2026-10-12', 'count' => 1])
        ->assertJsonPath('data.dailies', ['days' => 30, 'scheduled' => 30, 'completed' => 1, 'percent' => 3]);
});

it('has no share for a player without dailies', function () {
    // Act
    $response = $this->actingAs($this->user)->getJson('/api/stats');

    // Assert
    $response->assertOk()->assertJsonPath('data.dailies.percent', null);
});

it('reads the stats with a constant number of queries', function () {
    // Arrange
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->user)->getJson('/api/stats')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    Task::factory()->for($this->user)->daily()->create(['created_at' => '2026-08-01']);
    $withOneDaily = $countQueries();

    // Act
    Task::factory()->for($this->user)->daily()->count(5)->create(['created_at' => '2026-08-01'])
        ->each(fn (Task $task) => TaskCompletion::factory()->for($task)->on('2026-10-12')->create());
    $withSixDailies = $countQueries();

    // Assert
    expect($withSixDailies)->toBe($withOneDaily);
});

it('answers with the habits of the last thirty days', function () {
    // Arrange
    $habit = Task::factory()->for($this->user)->habit()->create(['title' => 'Пить воду']);
    HabitMark::factory()->for($habit)->on('2026-10-13')->create();

    // Act
    $response = $this->actingAs($this->user)->getJson('/api/stats');

    // Assert
    $response->assertOk()->assertJsonPath('data.habits', [
        ['id' => $habit->id, 'title' => 'Пить воду', 'up' => 1, 'down' => 0],
    ]);
});

it('requires authentication', function () {
    // Act & Assert
    $this->getJson('/api/stats')->assertUnauthorized();
});
