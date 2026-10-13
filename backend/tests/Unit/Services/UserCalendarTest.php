<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\UserCalendar;
use Carbon\CarbonImmutable;
use Tests\TestCase;

// now() and travelTo() need the application, the database does not
uses(TestCase::class);

it('returns the current date on the user wall clock', function (string $nowUtc, string $timezone, string $expected) {
    // Arrange
    $this->travelTo(CarbonImmutable::parse($nowUtc, 'UTC'));
    $user = new User(['timezone' => $timezone]);

    // Act
    $today = (new UserCalendar)->today($user);

    // Assert
    expect($today->toDateString())->toBe($expected);
})->with([
    'one second before Moscow midnight' => ['2026-10-13 20:59:59', 'Europe/Moscow', '2026-10-13'],
    'Moscow midnight' => ['2026-10-13 21:00:00', 'Europe/Moscow', '2026-10-14'],
    'behind UTC' => ['2026-10-13 03:00:00', 'America/Los_Angeles', '2026-10-12'],
    'UTC itself' => ['2026-10-13 12:00:00', 'UTC', '2026-10-13'],
    'fourteen hours ahead' => ['2026-10-13 11:30:00', 'Pacific/Kiritimati', '2026-10-14'],
]);

it('returns the start of the day in the user timezone', function () {
    // Arrange
    $this->travelTo(CarbonImmutable::parse('2026-10-13 15:45:10', 'UTC'));
    $user = new User(['timezone' => 'Europe/Moscow']);

    // Act
    $today = (new UserCalendar)->today($user);

    // Assert
    expect($today->timezoneName)->toBe('Europe/Moscow')
        ->and($today->toTimeString())->toBe('00:00:00');
});
