<?php

declare(strict_types=1);

use App\Services\ProgressionService;
use Tests\TestCase;

// config() needs the application, the database does not
uses(TestCase::class);

it('makes every level cost more XP than the one before', function (int $level, int $expected) {
    // Act
    $cost = (new ProgressionService)->xpToNextLevel($level);

    // Assert
    expect($cost)->toBe($expected);
})->with([
    'level 1' => [1, 50],
    'level 2' => [2, 141],
    'level 3' => [3, 260],
    'level 4' => [4, 400],
    'level 5' => [5, 559],
]);

it('keeps the curve strictly growing', function () {
    // Arrange
    $service = new ProgressionService;

    // Act
    $costs = array_map($service->xpToNextLevel(...), range(1, 30));

    // Assert
    foreach (array_slice($costs, 1) as $index => $cost) {
        expect($cost)->toBeGreaterThan($costs[$index]);
    }
});

it('derives the level and the XP inside it from the total', function (int $total, int $level, int $inLevel, int $toNext) {
    // Act
    $progress = (new ProgressionService)->progressFor($total);

    // Assert
    expect($progress->level)->toBe($level)
        ->and($progress->xpTotal)->toBe($total)
        ->and($progress->xpInLevel)->toBe($inLevel)
        ->and($progress->xpToNextLevel)->toBe($toNext);
})->with([
    'no XP yet' => [0, 1, 0, 50],
    'one short of level 2' => [49, 1, 49, 50],
    'exactly level 2' => [50, 2, 0, 141],
    'one short of level 3' => [190, 2, 140, 141],
    'exactly level 3' => [191, 3, 0, 260],
    'exactly level 4' => [451, 4, 0, 400],
]);

it('reads the curve from the config', function () {
    // Arrange
    config(['progression.levels.exponent' => 1.0]);

    // Act
    $cost = (new ProgressionService)->xpToNextLevel(2);

    // Assert
    expect($cost)->toBe(100);
});

it('never asks for less than one XP, so a broken config cannot loop forever', function () {
    // Arrange
    config(['progression.levels.base_xp' => 0]);

    // Act
    $progress = (new ProgressionService)->progressFor(3);

    // Assert
    expect($progress->level)->toBe(4)
        ->and($progress->xpToNextLevel)->toBe(1);
});
