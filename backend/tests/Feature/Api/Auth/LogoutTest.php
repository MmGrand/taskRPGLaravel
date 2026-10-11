<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs the user out', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $response = $this->actingAs($user)->postJson('/api/logout');

    // Assert
    $response->assertNoContent();
    $this->assertGuest('web');
});

it('rejects logout for guests', function () {
    // Act
    $response = $this->postJson('/api/logout');

    // Assert
    $response->assertUnauthorized();
});
