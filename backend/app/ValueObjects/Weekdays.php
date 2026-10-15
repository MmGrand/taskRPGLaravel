<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

// Days of the week a daily is due on, as a bit mask: bit 0 is Monday, bit 6 is Sunday (ISO 1-7)
final readonly class Weekdays
{
    private const int DAYS_IN_WEEK = 7;

    public const int EVERY_DAY_MASK = (1 << self::DAYS_IN_WEEK) - 1;

    private function __construct(public int $mask) {}

    public static function everyDay(): self
    {
        return new self(self::EVERY_DAY_MASK);
    }

    public static function fromMask(int $mask): self
    {
        if ($mask < 1 || $mask > self::EVERY_DAY_MASK) {
            throw new InvalidArgumentException("Invalid weekday mask {$mask}.");
        }

        return new self($mask);
    }

    /**
     * @param  list<int>  $days  ISO days 1-7, already validated by the request
     */
    public static function fromIsoDays(array $days): self
    {
        return self::fromMask(array_reduce($days, fn (int $mask, int $day): int => $mask | self::bit($day), 0));
    }

    // The weekday is taken in the zone of $day: UserCalendar::today() is already in the user's one
    public function includes(CarbonImmutable $day): bool
    {
        return ($this->mask & self::bit($day->isoWeekday())) !== 0;
    }

    /**
     * @return list<int>
     */
    public function isoDays(): array
    {
        return array_values(array_filter(
            range(1, self::DAYS_IN_WEEK),
            fn (int $day): bool => ($this->mask & self::bit($day)) !== 0,
        ));
    }

    private static function bit(int $isoDay): int
    {
        return 1 << ($isoDay - 1);
    }
}
