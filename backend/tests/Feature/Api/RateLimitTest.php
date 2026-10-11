<?php

declare(strict_types=1);

it('throttles api requests above the per-minute limit', function () {
    // Arrange
    $limit = (int) config('api.rate_limit_per_minute');

    for ($i = 0; $i < $limit; $i++) {
        $this->getJson('/api/health')->assertOk();
    }

    // Act
    $response = $this->getJson('/api/health');

    // Assert
    $response->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('exposes rate limit headers on api responses', function () {
    // Act
    $response = $this->getJson('/api/health');

    // Assert
    $response->assertOk()
        ->assertHeader('X-RateLimit-Limit', (string) config('api.rate_limit_per_minute'));
});
