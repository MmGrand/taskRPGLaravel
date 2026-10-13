<?php

declare(strict_types=1);

use App\Enums\ItemSlot;
use App\Models\Character;
use App\Models\Item;
use App\Models\User;
use App\Services\EquipmentService;
use App\Services\StarterKit;
use Database\Seeders\ItemCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('rejects guests', function () {
    // Act
    $response = $this->getJson('/api/user');

    // Assert
    $response->assertUnauthorized();
});

it('returns the authenticated user without a character', function () {
    // Arrange
    $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);

    // Act
    $response = $this->actingAs($user)->getJson('/api/user');

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.timezone', 'Asia/Tokyo')
        ->assertJsonPath('data.character', null);
});

it('returns the authenticated user with their character', function () {
    // Arrange
    $character = Character::factory()->create(['skin_tone' => 'green']);

    // Act
    $response = $this->actingAs($character->user)->getJson('/api/user');

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.character.id', $character->id)
        ->assertJsonPath('data.character.appearance.skin_tone', 'green')
        ->assertJsonPath('data.character.equipment', []);
});

it('returns the progress of the character', function () {
    // Arrange
    $character = Character::factory()->withXp(60, 25)->create();

    // Act
    $response = $this->actingAs($character->user)->getJson('/api/user');

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.character.progress', ['level' => 2, 'xp_total' => 60, 'xp_in_level' => 10, 'xp_to_next_level' => 141])
        ->assertJsonPath('data.character.gold', 25)
        ->assertJsonPath('data.character.streak', ['days' => 0, 'active_today' => false]);
});

it('loads the current user and the character without extra queries for the owner', function () {
    // Arrange
    $character = Character::factory()->create();
    $this->actingAs($character->user)->getJson('/api/user')->assertOk();
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    // Act
    $this->actingAs($character->user)->getJson('/api/user')->assertOk();

    // Assert: character, equipped items, streak days; the owner comes from chaperone()
    expect($queries)->toBe(3);
});

it('returns what the character wears', function () {
    // Arrange
    $this->seed(ItemCatalogSeeder::class);
    $character = Character::factory()->create();
    app(StarterKit::class)->grantTo($character);
    $sword = Item::query()->where('code', 'wooden_sword')->sole();
    app(EquipmentService::class)->equip($character, ItemSlot::Weapon, $sword);

    // Act
    $response = $this->actingAs($character->user)->getJson('/api/user');

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.character.equipment.0.slot', 'weapon')
        ->assertJsonPath('data.character.equipment.0.item.code', 'wooden_sword')
        ->assertJsonPath('data.character.equipment.0.item.sprite', ['col' => 42, 'row' => 6]);
});
