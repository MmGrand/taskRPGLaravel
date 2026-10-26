<?php

declare(strict_types=1);

use App\Models\Character;
use App\Services\StarterKit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stamps starter items with the application clock', function () {
    // Arrange
    $this->travelTo(CarbonImmutable::parse('2026-01-02 03:04:05', 'UTC'));
    $hero = Character::factory()->create();

    // Act
    app(StarterKit::class)->grantTo($hero);

    // Assert
    expect($hero->inventoryItems()->pluck('acquired_at')->map->toIso8601String()->unique()->all())
        ->toBe(['2026-01-02T03:04:05+00:00']);
});

it('grants the starter kit only to the given character', function () {
    // Arrange
    $hero = Character::factory()->create();
    $other = Character::factory()->create();

    // Act
    app(StarterKit::class)->grantTo($hero);

    // Assert
    expect($hero->inventoryItems()->count())->toBe(5)
        ->and($other->inventoryItems()->count())->toBe(0);
});

it('grants it once however many times it is asked', function () {
    // Arrange
    $hero = Character::factory()->create();

    // Act
    app(StarterKit::class)->grantTo($hero);
    app(StarterKit::class)->grantTo($hero);

    // Assert
    expect($hero->inventoryItems()->count())->toBe(5);
});
