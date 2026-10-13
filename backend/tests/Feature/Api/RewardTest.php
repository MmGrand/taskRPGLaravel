<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Reward;

// The namespace keeps this file's helper functions apart from those of other test files

use App\Models\Character;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
    $this->character = Character::factory()->for($this->user)->create();
    $this->daily = Task::factory()->for($this->user)->daily()->create();
});

function completionUrl(Task $task): string
{
    return "/api/tasks/{$task->id}/completion";
}

it('rewards a completion with XP and gold', function () {
    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.is_completed', true)
        ->assertJsonPath('reward', ['xp' => 10, 'gold' => 5])
        ->assertJsonPath('character.progress', ['level' => 1, 'xp_total' => 10, 'xp_in_level' => 10, 'xp_to_next_level' => 50])
        ->assertJsonPath('character.gold', 5)
        ->assertJsonPath('character.streak', ['days' => 1, 'active_today' => true]);
    expect($this->character->fresh())->xp->toBe(10)->gold->toBe(5);
    $this->assertDatabaseHas('task_completions', ['task_id' => $this->daily->id, 'xp_awarded' => 10, 'gold_awarded' => 5]);
});

it('gives no second reward for a repeated completion', function () {
    // Arrange
    $this->actingAs($this->user)->putJson(completionUrl($this->daily))->assertOk();

    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()->assertJsonPath('reward', null)->assertJsonPath('character.progress.xp_total', 10);
    expect($this->character->fresh()->xp)->toBe(10);
});

it('gives no reward when a parallel request already stored the completion', function () {
    // Arrange
    TaskCompletion::factory()->for($this->daily)->on('2026-10-13')->create();

    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()->assertJsonPath('reward', null);
    expect($this->character->fresh()->xp)->toBe(0);
});

it('takes the reward back when the completion is undone', function () {
    // Arrange
    $this->actingAs($this->user)->putJson(completionUrl($this->daily))->assertOk();

    // Act
    $response = $this->actingAs($this->user)->deleteJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.is_completed', false)
        ->assertJsonPath('reward', ['xp' => -10, 'gold' => -5])
        ->assertJsonPath('character.streak', ['days' => 0, 'active_today' => false]);
    expect($this->character->fresh())->xp->toBe(0)->gold->toBe(0);
    $this->assertDatabaseCount('task_completions', 0);
});

it('changes nothing when undoing a task that is not completed', function () {
    // Act
    $response = $this->actingAs($this->user)->deleteJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()->assertJsonPath('reward', null);
});

it('never takes gold below zero', function () {
    // Arrange: some of the gold was spent after the completion
    $this->actingAs($this->user)->putJson(completionUrl($this->daily))->assertOk();
    Character::query()->whereKey($this->character->id)->update(['gold' => 2]);

    // Act
    $response = $this->actingAs($this->user)->deleteJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()->assertJsonPath('reward', ['xp' => -10, 'gold' => -2]);
    expect($this->character->fresh())->xp->toBe(0)->gold->toBe(0);
});

it('raises the level along the curve', function () {
    // Arrange
    Character::query()->whereKey($this->character->id)->update(['xp' => 45]);

    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()
        ->assertJsonPath('character.progress', ['level' => 2, 'xp_total' => 55, 'xp_in_level' => 5, 'xp_to_next_level' => 141]);
});

it('lowers the level again when the completion that raised it is undone', function () {
    // Arrange
    Character::query()->whereKey($this->character->id)->update(['xp' => 45]);
    $this->actingAs($this->user)->putJson(completionUrl($this->daily))->assertOk();

    // Act
    $response = $this->actingAs($this->user)->deleteJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()
        ->assertJsonPath('character.progress', ['level' => 1, 'xp_total' => 45, 'xp_in_level' => 45, 'xp_to_next_level' => 50]);
});

it('adds the streak bonus day after day', function () {
    // Arrange
    TaskCompletion::factory()->for($this->daily)->on('2026-10-11')->create();
    TaskCompletion::factory()->for($this->daily)->on('2026-10-12')->create();

    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()
        ->assertJsonPath('reward', ['xp' => 12, 'gold' => 6])
        ->assertJsonPath('character.streak', ['days' => 3, 'active_today' => true]);
});

it('rewards every task completed on the same day', function () {
    // Arrange
    $todo = Task::factory()->for($this->user)->todo()->create();
    $this->actingAs($this->user)->putJson(completionUrl($this->daily))->assertOk();

    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($todo));

    // Assert
    $response->assertOk()->assertJsonPath('reward', ['xp' => 10, 'gold' => 5]);
    expect($this->character->fresh())->xp->toBe(20)->gold->toBe(10);
});

it('completes tasks of a user without a character but rewards nobody', function () {
    // Arrange
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->daily()->create();

    // Act
    $response = $this->actingAs($user)->putJson(completionUrl($task));

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.is_completed', true)
        ->assertJsonPath('character', null)
        ->assertJsonPath('reward', null);
    $this->assertDatabaseHas('task_completions', ['task_id' => $task->id, 'xp_awarded' => 0, 'gold_awarded' => 0]);
});

it('keeps the reward of a todo completed on an earlier day', function () {
    // Arrange
    $todo = Task::factory()->for($this->user)->todo()->create();
    TaskCompletion::factory()->for($todo)->on('2026-10-12')->create(['xp_awarded' => 10, 'gold_awarded' => 5]);
    Character::query()->whereKey($this->character->id)->update(['xp' => 10, 'gold' => 5]);

    // Act
    $response = $this->actingAs($this->user)->deleteJson(completionUrl($todo));

    // Assert
    $response->assertOk()->assertJsonPath('reward', null);
    expect($this->character->fresh())->xp->toBe(10)->gold->toBe(5);
});

it('rewards no one for a foreign task', function () {
    // Arrange
    $foreign = Task::factory()->for(Character::factory()->create()->user)->daily()->create();

    // Act & Assert
    $this->actingAs($this->user)->putJson(completionUrl($foreign))->assertNotFound();
    expect(Character::query()->sum('xp'))->toBe(0);
});
