<?php

declare(strict_types=1);

use App\Services\RewardService;
use Tests\TestCase;

// config() needs the application, the database does not
uses(TestCase::class);

it('adds the streak bonus to the base reward', function (int $streakDays, int $xp, int $gold) {
    // Act
    $reward = (new RewardService)->rewardFor($streakDays);

    // Assert
    expect($reward->xp)->toBe($xp)
        ->and($reward->gold)->toBe($gold);
})->with([
    'first day' => [1, 10, 5],
    'second day, rounded down' => [2, 11, 5],
    'third day' => [3, 12, 6],
    'fifth day' => [5, 14, 7],
    'eleventh day hits the cap' => [11, 20, 10],
    'far past the cap' => [30, 20, 10],
    'no streak at all' => [0, 10, 5],
]);

it('reads rewards and the bonus from the config', function () {
    // Arrange
    config([
        'progression.streak_bonus.percent_per_day' => 5,
        'progression.streak_bonus.max_percent' => 50,
    ]);

    // Act
    $reward = (new RewardService)->rewardFor(30);

    // Assert
    expect($reward->xp)->toBe(15)
        ->and($reward->gold)->toBe(7);
});
