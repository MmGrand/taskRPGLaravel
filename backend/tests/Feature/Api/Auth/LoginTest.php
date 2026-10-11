<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['password' => 'correct-horse-battery']);
});

it('logs in with valid credentials', function () {
    // Act
    $response = $this->postJson('/api/login', [
        'email' => $this->user->email,
        'password' => 'correct-horse-battery',
    ]);

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.email', $this->user->email)
        ->assertJsonPath('data.character', null);
    $this->assertAuthenticatedAs($this->user);
});

it('accepts the email in any letter case', function () {
    // Arrange
    $user = User::factory()->create(['email' => 'hero@example.com', 'password' => 'correct-horse-battery']);

    // Act
    $response = $this->postJson('/api/login', [
        'email' => '  Hero@Example.COM ',
        'password' => 'correct-horse-battery',
    ]);

    // Assert
    $response->assertOk();
    $this->assertAuthenticatedAs($user);
});

it('returns the character together with the user', function () {
    // Arrange
    Character::factory()->for($this->user)->create(['name' => 'Torin']);

    // Act
    $response = $this->postJson('/api/login', [
        'email' => $this->user->email,
        'password' => 'correct-horse-battery',
    ]);

    // Assert
    $response->assertOk()->assertJsonPath('data.character.name', 'Torin');
});

it('rejects a wrong password', function () {
    // Act
    $response = $this->postJson('/api/login', [
        'email' => $this->user->email,
        'password' => 'wrong-password',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
    $this->assertGuest();
});

it('locks out after too many failed attempts even with the right password', function () {
    // Arrange
    $maxAttempts = (int) config('auth.throttle.login_max_attempts');

    for ($i = 0; $i < $maxAttempts; $i++) {
        $this->postJson('/api/login', ['email' => $this->user->email, 'password' => 'wrong-password'])
            ->assertUnprocessable();
    }

    // Act
    $response = $this->postJson('/api/login', [
        'email' => $this->user->email,
        'password' => 'correct-horse-battery',
    ]);

    // Assert
    $response->assertTooManyRequests()->assertJsonValidationErrors(['email']);
    $this->assertGuest();
});
