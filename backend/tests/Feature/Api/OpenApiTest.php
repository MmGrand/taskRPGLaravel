<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

it('documents every api route', function () {
    // Arrange
    $routes = collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/'));

    // Act
    $paths = $this->getJson('/docs/api.json')->assertOk()->json('paths');

    // Assert: Scramble drops the "api" prefix, the server URL carries it
    expect($routes)->not->toBeEmpty();
    $routes->each(function (Route $route) use ($paths): void {
        $path = '/'.substr($route->uri(), strlen('api/'));
        expect($paths)->toHaveKey($path);

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            expect($paths[$path])->toHaveKey(strtolower($method));
        }
    });
});

it('marks only sign-in free routes as public', function () {
    // Act
    $paths = $this->getJson('/docs/api.json')->assertOk()->json('paths');

    // Assert: everything else needs the SPA session cookie
    $public = collect($paths)
        ->flatMap(fn (array $operations, string $path): array => collect($operations)
            ->filter(fn (array $operation): bool => ($operation['security'] ?? null) === [])
            ->keys()
            ->map(fn (string $method): string => strtoupper($method).' '.$path)
            ->all())
        ->sort()
        ->values()
        ->all();

    expect($public)->toBe(['GET /health', 'POST /login', 'POST /register']);
});

it('shows the docs page to a guest', function () {
    // Act
    $response = $this->get('/docs/api');

    // Assert
    $response->assertOk();
});
