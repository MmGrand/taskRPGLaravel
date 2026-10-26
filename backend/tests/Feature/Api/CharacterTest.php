<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->payload = [
        'name' => 'Torin',
        'skin_tone' => 'tan',
        'hair_style' => 'braids',
        'hair_color' => 'ginger',
        'beard' => 'full',
    ];
});

it('creates a character for the authenticated user', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/character', $this->payload);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.name', 'Torin')
        ->assertJsonPath('data.appearance', [
            'skin_tone' => 'tan',
            'hair_style' => 'braids',
            'hair_color' => 'ginger',
            'beard' => 'full',
        ]);
    $this->assertDatabaseCount('characters', 1);
    $this->assertDatabaseHas('characters', ['user_id' => $this->user->id, 'name' => 'Torin']);
});

it('gives a new character the starter kit without wearing it', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/character', $this->payload);

    // Assert
    $response->assertCreated()->assertJsonPath('data.equipment', []);
    $character = Character::query()->sole();
    expect($character->inventoryItems()->with('item')->get()->pluck('item.code')->sort()->values()->all())
        ->toBe(['cloth_headband', 'leather_pants', 'teal_shirt', 'wooden_buckler', 'wooden_sword']);
    $this->assertDatabaseCount('equipped_items', 0);
});

it('starts a new character at level 1 with no gold and no streak', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/character', $this->payload);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.progress', ['level' => 1, 'xp_total' => 0, 'xp_in_level' => 0, 'xp_to_next_level' => 50])
        ->assertJsonPath('data.gold', 0)
        ->assertJsonPath('data.streak', ['days' => 0, 'active_today' => false]);
});

it('accepts non-latin names', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/character', [...$this->payload, 'name' => 'Торин Дубощит']);

    // Assert
    $response->assertCreated()->assertJsonPath('data.name', 'Торин Дубощит');
});

it('refuses to create a second character', function () {
    // Arrange
    Character::factory()->for($this->user)->create();

    // Act
    $response = $this->actingAs($this->user)->postJson('/api/character', $this->payload);

    // Assert
    $response->assertConflict();
    $this->assertDatabaseCount('characters', 1);
});

it('rejects invalid character data', function (array $overrides, string $field) {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/character', [...$this->payload, ...$overrides]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors([$field]);
    $this->assertDatabaseCount('characters', 0);
})->with([
    'empty name' => [['name' => ''], 'name'],
    'too short name' => [['name' => 'A'], 'name'],
    'too long name' => [['name' => str_repeat('a', 25)], 'name'],
    'markup in name' => [['name' => '<script>'], 'name'],
    'unknown skin tone' => [['skin_tone' => 'blue'], 'skin_tone'],
    'unknown hair style' => [['hair_style' => 'afro'], 'hair_style'],
    'unknown hair color' => [['hair_color' => 'purple'], 'hair_color'],
    'missing beard' => [['beard' => null], 'beard'],
]);

it('returns 404 when the user has no character yet', function () {
    // Act
    $response = $this->actingAs($this->user)->getJson('/api/character');

    // Assert
    $response->assertNotFound();
});

it('returns only the character of the authenticated user', function () {
    // Arrange
    $own = Character::factory()->for($this->user)->create();
    Character::factory()->create();

    // Act
    $response = $this->actingAs($this->user)->getJson('/api/character');

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.id', $own->id)
        ->assertJsonPath('data.equipment', []);
});

it('changes the name and look of the character', function () {
    // Arrange
    $character = Character::factory()->for($this->user)->create(['name' => 'Старое имя']);

    // Act
    $response = $this->actingAs($this->user)->putJson('/api/character', $this->payload);

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.id', $character->id)
        ->assertJsonPath('data.name', 'Torin')
        ->assertJsonPath('data.appearance', [
            'skin_tone' => 'tan',
            'hair_style' => 'braids',
            'hair_color' => 'ginger',
            'beard' => 'full',
        ]);
    $this->assertDatabaseHas('characters', ['id' => $character->id, 'name' => 'Torin', 'hair_style' => 'braids']);
});

it('keeps progress and gold when the look changes', function () {
    // Arrange
    Character::factory()->for($this->user)->withXp(60, 25)->create();

    // Act: earned values cannot be sent along
    $response = $this->actingAs($this->user)->putJson('/api/character', [...$this->payload, 'xp' => 9999, 'gold' => 9999]);

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.progress.xp_total', 60)
        ->assertJsonPath('data.gold', 25);
});

it('rejects invalid character changes', function (array $overrides, string $field) {
    // Arrange
    Character::factory()->for($this->user)->create(['name' => 'Торин']);

    // Act
    $response = $this->actingAs($this->user)->putJson('/api/character', [...$this->payload, ...$overrides]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors([$field]);
    $this->assertDatabaseHas('characters', ['user_id' => $this->user->id, 'name' => 'Торин']);
})->with([
    'too short name' => [['name' => 'A'], 'name'],
    'markup in name' => [['name' => '<script>'], 'name'],
    'unknown hair style' => [['hair_style' => 'afro'], 'hair_style'],
    'missing beard' => [['beard' => null], 'beard'],
]);

it('returns 404 when changing a character that does not exist', function () {
    // Act
    $response = $this->actingAs($this->user)->putJson('/api/character', $this->payload);

    // Assert
    $response->assertNotFound();
});

it('requires authentication for character endpoints', function () {
    // Act & Assert
    $this->getJson('/api/character')->assertUnauthorized();
    $this->postJson('/api/character', $this->payload)->assertUnauthorized();
    $this->putJson('/api/character', $this->payload)->assertUnauthorized();
});
