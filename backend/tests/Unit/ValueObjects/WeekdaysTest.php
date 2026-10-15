<?php

declare(strict_types=1);

use App\ValueObjects\Weekdays;
use Carbon\CarbonImmutable;

it('builds the mask from ISO days', function () {
    // Act
    $weekdays = Weekdays::fromIsoDays([5, 1, 3]);

    // Assert
    expect($weekdays->mask)->toBe(21)
        ->and($weekdays->isoDays())->toBe([1, 3, 5]);
});

it('covers every day by default', function () {
    // Act
    $weekdays = Weekdays::everyDay();

    // Assert
    expect($weekdays->mask)->toBe(Weekdays::EVERY_DAY_MASK)
        ->and($weekdays->isoDays())->toBe([1, 2, 3, 4, 5, 6, 7]);
});

it('tells whether a day is on the schedule', function (string $date, bool $expected) {
    // Arrange
    $weekdays = Weekdays::fromIsoDays([1, 3, 5]);

    // Act
    $includes = $weekdays->includes(CarbonImmutable::parse($date));

    // Assert
    expect($includes)->toBe($expected);
})->with([
    'monday' => ['2026-10-12', true],
    'tuesday' => ['2026-10-13', false],
    'friday' => ['2026-10-16', true],
    'sunday' => ['2026-10-18', false],
]);

it('takes the weekday in the zone of the given day', function () {
    // Arrange: 21:30 UTC on Tuesday is already Wednesday in Moscow
    $day = CarbonImmutable::parse('2026-10-13 21:30:00', 'UTC')->setTimezone('Europe/Moscow');

    // Act & Assert
    expect(Weekdays::fromIsoDays([3])->includes($day))->toBeTrue();
});

it('rejects a mask outside the week', function (int $mask) {
    // Act & Assert
    expect(fn () => Weekdays::fromMask($mask))->toThrow(InvalidArgumentException::class);
})->with(['no days' => 0, 'eighth day' => 128, 'negative' => -1]);
