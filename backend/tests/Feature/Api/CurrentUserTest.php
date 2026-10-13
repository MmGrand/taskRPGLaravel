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
