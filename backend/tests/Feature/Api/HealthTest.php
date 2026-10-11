<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

it('returns ok when all dependencies are reachable', function () {
    // Act
    $response = $this->getJson('/api/health');

    // Assert
    $response->assertOk()
        ->assertExactJson(['data' => ['status' => 'ok', 'checks' => ['database' => 'ok', 'redis' => 'ok']]]);
});

it('returns 503 and logs when the database is unreachable', function () {
    // Arrange
    DB::shouldReceive('select')->once()->andThrow(new RuntimeException('connection refused'));
    Log::shouldReceive('error')->once()->withArgs(
        fn (string $message, array $context): bool => $message === 'Health check failed' && $context['check'] === 'database'
    );

    // Act
    $response = $this->getJson('/api/health');

    // Assert
    $response->assertStatus(503)
        ->assertJsonPath('data.status', 'degraded')
        ->assertJsonPath('data.checks.database', 'fail')
        ->assertJsonPath('data.checks.redis', 'ok');
});

it('responds with json for unknown api routes without an Accept header', function () {
    // Act
    $response = $this->get('/api/does-not-exist');

    // Assert
    $response->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});
