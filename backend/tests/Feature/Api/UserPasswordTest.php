<?php

declare(strict_types=1);

namespace Tests\Feature\Api\UserPassword;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

const NEW_PASSWORD = 'new-secret-123';

beforeEach(function () {
    // The factory password is "password"
    $this->user = User::factory()->create();
    $this->payload = [
        'current_password' => 'password',
        'password' => NEW_PASSWORD,
        'password_confirmation' => NEW_PASSWORD,
    ];
});

it('changes the password', function () {
    // Arrange
    $rememberToken = $this->user->remember_token;

    // Act
    $response = $this->actingAs($this->user)->putJson('/api/user/password', $this->payload);

    // Assert
    $response->assertNoContent();
    $user = $this->user->refresh();
    expect(Hash::check(NEW_PASSWORD, $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($rememberToken);
});

it('keeps the current session signed in after the change', function () {
    // Arrange
    $this->actingAs($this->user)->putJson('/api/user/password', $this->payload)->assertNoContent();

    // Act
    $response = $this->getJson('/api/user');

    // Assert
    $response->assertOk()->assertJsonPath('data.id', $this->user->id);
});

it('signs other devices out after the change', function () {
    // Arrange: another device remembers the hash of the old password in its session
    $oldHash = $this->user->password;
    $this->actingAs($this->user)->putJson('/api/user/password', $this->payload)->assertNoContent();

    // Act
    $response = $this->withSession(['password_hash_web' => $oldHash])
        ->actingAs($this->user->refresh())
        ->getJson('/api/user');

    // Assert
    $response->assertUnauthorized();
});

it('rejects a wrong current password', function () {
    // Act
    $response = $this->actingAs($this->user)->putJson('/api/user/password', [...$this->payload, 'current_password' => 'wrong-password']);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['current_password' => 'Неверный пароль.']);
    expect(Hash::check('password', $this->user->refresh()->password))->toBeTrue();
});

it('rejects invalid new passwords', function (array $overrides, string $field) {
    // Act
    $response = $this->actingAs($this->user)->putJson('/api/user/password', [...$this->payload, ...$overrides]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors([$field]);
    expect(Hash::check('password', $this->user->refresh()->password))->toBeTrue();
})->with([
    'not confirmed' => [['password_confirmation' => 'something-else'], 'password'],
    'too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'same as current' => [['password' => 'password', 'password_confirmation' => 'password'], 'password'],
    'missing current' => [['current_password' => ''], 'current_password'],
]);

it('throttles password change attempts', function () {
    // Arrange
    $limit = (int) config('auth.throttle.password_change_per_minute');
    $wrong = [...$this->payload, 'current_password' => 'wrong-password'];

    for ($i = 0; $i < $limit; $i++) {
        $this->actingAs($this->user)->putJson('/api/user/password', $wrong)->assertUnprocessable();
    }

    // Act
    $response = $this->actingAs($this->user)->putJson('/api/user/password', $wrong);

    // Assert
    $response->assertTooManyRequests();
});

it('requires authentication', function () {
    // Act & Assert
    $this->putJson('/api/user/password', $this->payload)->assertUnauthorized();
});
