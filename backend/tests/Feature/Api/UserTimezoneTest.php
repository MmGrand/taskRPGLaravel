<?php

declare(strict_types=1);

namespace Tests\Feature\Api\UserTimezone;

use App\Models\Character;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
});

it('changes the timezone', function () {
    // Act
    $response = $this->actingAs($this->user)->putJson('/api/user/timezone', ['timezone' => 'Asia/Tokyo']);

    // Assert
    $response->assertOk()->assertJsonPath('data.timezone', 'Asia/Tokyo');
    $user = $this->user->refresh();
    expect($user->timezone)->toBe('Asia/Tokyo')
        ->and($user->timezone_changed_at?->equalTo(now()))->toBeTrue();
});

it('counts today by the new timezone right away', function () {
    // Arrange: done at 15:00 in Moscow on the 13th
    Character::factory()->for($this->user)->create();
    $daily = Task::factory()->for($this->user)->daily()->create();
    $this->actingAs($this->user)->putJson("/api/tasks/{$daily->id}/completion")->assertOk();

    // Act: 19:00 in Moscow is already 06:00 of the 14th on Kiritimati (UTC+14)
    $this->travelTo(CarbonImmutable::parse('2026-10-13 16:00:00', 'UTC'));
    $this->actingAs($this->user)->putJson('/api/user/timezone', ['timezone' => 'Pacific/Kiritimati'])->assertOk();
    // A real request loads the user afresh; the test reuses one model, so it is reloaded by hand
    $this->user->refresh();
    $list = $this->actingAs($this->user)->getJson('/api/tasks');
    $completion = $this->actingAs($this->user)->putJson("/api/tasks/{$daily->id}/completion");

    // Assert
    expect(collect($list->json('data'))->firstWhere('id', $daily->id)['is_completed'])->toBeFalse();
    $completion->assertOk()->assertJsonPath('data.is_completed', true);
    $this->assertDatabaseHas('task_completions', ['task_id' => $daily->id, 'period_date' => '2026-10-14']);
});

it('returns the character streak on the new calendar', function () {
    // Arrange: active on the 13th in Moscow
    Character::factory()->for($this->user)->create();
    $daily = Task::factory()->for($this->user)->daily()->create();
    $this->actingAs($this->user)->putJson("/api/tasks/{$daily->id}/completion")->assertOk();
    $this->travelTo(CarbonImmutable::parse('2026-10-13 16:00:00', 'UTC'));

    // Act: on Kiritimati it is the 14th, so the 13th is yesterday
    $response = $this->actingAs($this->user)->putJson('/api/user/timezone', ['timezone' => 'Pacific/Kiritimati']);

    // Assert
    $response->assertOk()->assertJsonPath('data.character.streak', ['days' => 1, 'active_today' => false]);
});

it('refuses a second change within the cooldown', function () {
    // Arrange
    $this->actingAs($this->user)->putJson('/api/user/timezone', ['timezone' => 'Asia/Tokyo'])->assertOk();
    $this->travel(23)->hours();

    // Act
    $response = $this->actingAs($this->user)->putJson('/api/user/timezone', ['timezone' => 'Europe/Berlin']);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['timezone' => 'Часовой пояс недавно менялся. Попробуй через 1 ч.']);
    expect($this->user->refresh()->timezone)->toBe('Asia/Tokyo');
});

it('allows a change once the cooldown is over', function () {
    // Arrange
    $this->actingAs($this->user)->putJson('/api/user/timezone', ['timezone' => 'Asia/Tokyo'])->assertOk();
    $this->travel(24)->hours();

    // Act
    $response = $this->actingAs($this->user)->putJson('/api/user/timezone', ['timezone' => 'Europe/Berlin']);

    // Assert
    $response->assertOk()->assertJsonPath('data.timezone', 'Europe/Berlin');
});

it('does not start the cooldown when the timezone stays the same', function () {
    // Act
    $same = $this->actingAs($this->user)->putJson('/api/user/timezone', ['timezone' => 'Europe/Moscow']);
    $next = $this->actingAs($this->user)->putJson('/api/user/timezone', ['timezone' => 'Asia/Tokyo']);

    // Assert
    $same->assertOk()->assertJsonPath('data.timezone', 'Europe/Moscow');
    $next->assertOk()->assertJsonPath('data.timezone', 'Asia/Tokyo');
});

it('accepts a backward-compatible timezone name', function () {
    // Act
    $response = $this->actingAs($this->user)->putJson('/api/user/timezone', ['timezone' => 'Asia/Calcutta']);

    // Assert
    $response->assertOk()->assertJsonPath('data.timezone', 'Asia/Calcutta');
});

it('rejects an invalid timezone', function (mixed $timezone) {
    // Act
    $response = $this->actingAs($this->user)->putJson('/api/user/timezone', ['timezone' => $timezone]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['timezone']);
    expect($this->user->refresh()->timezone)->toBe('Europe/Moscow');
})->with([
    'unknown' => ['Mars/Olympus'],
    'missing' => [null],
    'empty' => [''],
]);

it('requires authentication', function () {
    // Act & Assert
    $this->putJson('/api/user/timezone', ['timezone' => 'Asia/Tokyo'])->assertUnauthorized();
});
