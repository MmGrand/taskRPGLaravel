<?php

declare(strict_types=1);

use App\ValueObjects\Reward;

it('describes a completion past the daily limit as capped and empty', function () {
    // Act
    $reward = Reward::capped();

    // Assert
    expect($reward->xp)->toBe(0)
        ->and($reward->gold)->toBe(0)
        ->and($reward->capped)->toBeTrue();
});

it('is not capped by default', function () {
    // Act
    $reward = new Reward(10, 5);

    // Assert
    expect($reward->capped)->toBeFalse()
        ->and(Reward::none()->capped)->toBeFalse();
});

it('drops the cap when taken back: undoing a mark is no longer about the limit', function () {
    // Act
    $taken = (new Reward(10, 5, capped: true))->negated();

    // Assert
    expect($taken->toArray())->toBe(['xp' => -10, 'gold' => -5, 'capped' => false]);
});

it('serialises all three fields', function () {
    // Act
    $array = Reward::capped()->toArray();

    // Assert
    expect($array)->toBe(['xp' => 0, 'gold' => 0, 'capped' => true]);
});
