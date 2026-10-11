<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->payload = [
        'email' => 'hero@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'timezone' => 'Europe/Moscow',
    ];
});

it('registers a user, logs them in and returns 201', function () {
    // Act
    $response = $this->postJson('/api/register', $this->payload);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.email', 'hero@example.com')
        ->assertJsonPath('data.timezone', 'Europe/Moscow')
        ->assertJsonPath('data.character', null)
        ->assertJsonMissingPath('data.password');
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'hero@example.com', 'timezone' => 'Europe/Moscow']);
});

it('normalizes the email to lowercase', function () {
    // Act
    $response = $this->postJson('/api/register', [...$this->payload, 'email' => '  Hero@Example.COM ']);

    // Assert
    $response->assertCreated()->assertJsonPath('data.email', 'hero@example.com');
});

it('returns validation messages in Russian', function () {
    // Act
    $response = $this->postJson('/api/register', [...$this->payload, 'timezone' => 'Mars/Olympus']);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors([
        'timezone' => 'Значение поля часовой пояс должно быть действительным часовым поясом.',
    ]);
});

it('rejects invalid registration data', function (array $overrides, string $field) {
    // Arrange
    User::factory()->create(['email' => 'taken@example.com']);

    // Act
    $response = $this->postJson('/api/register', [...$this->payload, ...$overrides]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors([$field]);
    $this->assertGuest();
})->with([
    'duplicate email' => [['email' => 'taken@example.com'], 'email'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'password mismatch' => [['password_confirmation' => 'different-password'], 'password'],
    'unknown timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'missing timezone' => [['timezone' => null], 'timezone'],
]);
