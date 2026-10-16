<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Achievement;

// The namespace keeps this file's helper functions apart from those of other test files

use App\Models\Character;
use App\Models\Item;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use App\Services\StarterKit;
use Carbon\CarbonImmutable;
use Database\Seeders\ItemCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const LEVEL_TWO_XP = 50;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->seed(ItemCatalogSeeder::class);
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
    $this->character = Character::factory()->for($this->user)->create();
    app(StarterKit::class)->grantTo($this->character);
    $this->daily = Task::factory()->for($this->user)->daily()->create();
});

// A run of completed days that ended yesterday
function historyEndingYesterday(Task $task, int $days): void
{
    for ($day = 1; $day <= $days; $day++) {
        TaskCompletion::factory()->for($task)->on(CarbonImmutable::parse('2026-10-13')->subDays($day)->toDateString())->create();
    }
}

it('lists every achievement with its progress in catalog order', function () {
    // Act
    $response = $this->actingAs($this->user)->getJson('/api/achievements');

    // Assert
    $response->assertOk()
        ->assertJsonCount(13, 'data')
        ->assertJsonPath('data.0', [
            'code' => 'first_step',
            'title' => 'Первый шаг',
            'description' => 'Выполнить первую задачу',
            'current' => 0,
            'target' => 1,
            'earned_at' => null,
            'reward_item' => null,
        ])
        ->assertJsonPath('data.2.code', 'streak_7')
        ->assertJsonPath('data.2.reward_item.code', 'paladin_pants')
        ->assertJsonPath('data.2.reward_item.is_rare', true);
});

it('earns the history made before achievements existed when they are read', function () {
    // Arrange
    historyEndingYesterday($this->daily, 8);

    // Act
    $response = $this->actingAs($this->user)->getJson('/api/achievements');

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.2.earned_at', '2026-10-13T12:00:00+00:00')
        ->assertJsonPath('data.2.current', 7);
    expect($this->character->inventoryItems()->whereRelation('item', 'code', 'paladin_pants')->exists())->toBeTrue();
});

it('returns the achievements a completion earned', function () {
    // Act
    $response = $this->actingAs($this->user)->putJson("/api/tasks/{$this->daily->id}/completion");

    // Assert
    $response->assertOk()
        ->assertJsonCount(1, 'achievements')
        ->assertJsonPath('achievements.0.code', 'first_step');
});

it('returns no achievements for a completion that earned none', function () {
    // Arrange
    $this->actingAs($this->user)->putJson("/api/tasks/{$this->daily->id}/completion")->assertOk();
    $todo = Task::factory()->for($this->user)->todo()->create();

    // Act
    $response = $this->actingAs($this->user)->putJson("/api/tasks/{$todo->id}/completion");

    // Assert
    $response->assertOk()->assertJsonPath('achievements', []);
});

it('returns the achievement a week of streak earned with its rare item', function () {
    // Arrange
    historyEndingYesterday($this->daily, 6);
    $this->actingAs($this->user)->getJson('/api/achievements')->assertOk();

    // Act
    $response = $this->actingAs($this->user)->putJson("/api/tasks/{$this->daily->id}/completion");

    // Assert
    $response->assertJsonPath('achievements.0.code', 'streak_7')
        ->assertJsonPath('achievements.0.reward_item.name', 'Штаны паладина');
});

it('returns the achievements a habit plus earned', function () {
    // Arrange
    $habit = Task::factory()->for($this->user)->habit()->create();

    // Act
    $response = $this->actingAs($this->user)->postJson("/api/tasks/{$habit->id}/habit-marks", ['direction' => 'up']);

    // Assert
    $response->assertCreated()->assertJsonPath('achievements.0.code', 'first_step');
});

it('checks no achievements on a minus, which is no activity', function () {
    // Arrange
    $habit = Task::factory()->for($this->user)->habit()->create();

    // Act
    $response = $this->actingAs($this->user)->postJson("/api/tasks/{$habit->id}/habit-marks", ['direction' => 'down']);

    // Assert
    $response->assertCreated()->assertJsonPath('achievements', []);
    expect($this->user->achievements()->count())->toBe(0);
});

it('checks no achievements on an undo', function () {
    // Arrange
    historyEndingYesterday($this->daily, 6);

    // Act
    $response = $this->actingAs($this->user)->deleteJson("/api/tasks/{$this->daily->id}/completion");

    // Assert
    $response->assertOk()->assertJsonPath('achievements', []);
    expect($this->user->achievements()->count())->toBe(0);
});

it('returns the first purchase in the purchase response', function () {
    // Arrange
    Character::query()->whereKey($this->character->id)->update(['xp' => LEVEL_TWO_XP, 'gold' => 30]);
    $bandana = Item::query()->where('code', 'green_bandana')->sole();

    // Act
    $response = $this->actingAs($this->user)->postJson('/api/shop/purchases', ['item_id' => $bandana->id]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.gold', 0)
        ->assertJsonPath('achievements.0.code', 'first_purchase');
});

it('shows the next goal in the character', function () {
    // Arrange: nine days in a row are closest to ten active days
    historyEndingYesterday($this->daily, 9);

    // Act
    $response = $this->actingAs($this->user)->getJson('/api/character');

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.next_goal.code', 'active_10')
        ->assertJsonPath('data.next_goal.current', 9)
        ->assertJsonPath('data.next_goal.target', 10);
});

it('requires authentication', function () {
    // Act
    $response = $this->getJson('/api/achievements');

    // Assert
    $response->assertUnauthorized();
});
