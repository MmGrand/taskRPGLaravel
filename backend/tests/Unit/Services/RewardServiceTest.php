<?php

declare(strict_types=1);

use App\Enums\TaskDifficulty;
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

it('scales the reward by difficulty', function (TaskDifficulty $difficulty, int $streakDays, int $xp, int $gold) {
    // Act
    $reward = (new RewardService)->rewardFor($streakDays, $difficulty);

    // Assert
    expect($reward->xp)->toBe($xp)
        ->and($reward->gold)->toBe($gold);
})->with([
    'easy, rounded down' => [TaskDifficulty::Easy, 1, 5, 2],
    'easy, second day' => [TaskDifficulty::Easy, 2, 5, 2],
    'medium is the base' => [TaskDifficulty::Medium, 1, 10, 5],
    'hard doubles' => [TaskDifficulty::Hard, 1, 20, 10],
    'hard with the full bonus' => [TaskDifficulty::Hard, 11, 40, 20],
]);

it('reads difficulty percents from the config', function () {
    // Arrange
    config(['progression.difficulty.hard' => 300]);

    // Act
    $reward = (new RewardService)->rewardFor(1, TaskDifficulty::Hard);

    // Assert
    expect($reward->xp)->toBe(30)
        ->and($reward->gold)->toBe(15);
});
