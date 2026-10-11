<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\User;
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
        ->assertJsonPath('data.character.appearance.skin_tone', 'green');
});
