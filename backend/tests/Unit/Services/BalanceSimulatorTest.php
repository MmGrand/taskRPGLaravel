<?php

declare(strict_types=1);

namespace Tests\Unit\Services\BalanceSimulator;

// The namespace keeps this file's helper functions apart from those of other test files

use App\Enums\TaskDifficulty;
use App\Models\Item;
use App\Services\BalanceSimulator;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

// config() needs the application, the database does not: the catalog is made in memory
uses(TestCase::class);

/**
 * @return Collection<int, Item>
 */
function catalog(): Collection
{
    return new Collection([
        Item::factory()->starter()->make(['id' => 1, 'name' => 'Повязка']),
        Item::factory()->make(['id' => 2, 'name' => 'Шлем', 'required_level' => 2, 'price' => 20]),
        Item::factory()->make(['id' => 3, 'name' => 'Бандана', 'required_level' => 2, 'price' => 10]),
    ]);
}

it('reaches levels on the days the rewards add up to', function () {
    // Act: 10 + 11 + 12 + 13 = 46 XP after day 4, 60 after day 5, and level 2 costs 50
    $simulation = app(BalanceSimulator::class)->simulate(1, 10, TaskDifficulty::Medium, catalog());

    // Assert
    expect($simulation->levelDays[2])->toBe(5)
        ->and($simulation->finalLevel)->toBe(2);
});

it('buys the cheapest unlocked item first and never the starter', function () {
    // Act: gold 5 + 5 + 6 + 6 + 7 = 29 by day 5, then 7 more on day 6
    $simulation = app(BalanceSimulator::class)->simulate(1, 12, TaskDifficulty::Medium, catalog());

    // Assert
    $purchases = array_values(array_filter($simulation->events, fn ($event) => $event->kind === 'purchase'));
    expect(array_map(fn ($event) => [$event->day, $event->text], $purchases))->toBe([
        [5, 'Bought Бандана (10), gold left 19'],
        [6, 'Bought Шлем (20), gold left 6'],
    ])
        ->and($simulation->catalogDay)->toBe(6);
});

it('names the items a new level unlocks', function () {
    // Act
    $simulation = app(BalanceSimulator::class)->simulate(1, 5, TaskDifficulty::Medium, catalog());

    // Assert
    expect($simulation->events[0]->day)->toBe(5)
        ->and($simulation->events[0]->kind)->toBe('level')
        ->and($simulation->events[0]->text)->toBe('Level 2, unlocked: Шлем, Бандана');
});

it('rewards no more completions than the daily limit', function () {
    // Act
    $limited = app(BalanceSimulator::class)->simulate(20, 30, TaskDifficulty::Medium, catalog());
    $atLimit = app(BalanceSimulator::class)->simulate(10, 30, TaskDifficulty::Medium, catalog());

    // Assert
    expect($limited->levelDays)->toBe($atLimit->levelDays);
});

it('levels up faster with hard tasks', function () {
    // Act
    $medium = app(BalanceSimulator::class)->simulate(1, 10, TaskDifficulty::Medium, catalog());
    $hard = app(BalanceSimulator::class)->simulate(1, 10, TaskDifficulty::Hard, catalog());

    // Assert: 20 + 22 + 24 = 66 XP after day 3
    expect($hard->levelDays[2])->toBe(3)
        ->and($hard->levelDays[2])->toBeLessThan($medium->levelDays[2]);
});

it('leaves the catalog unfinished when the days run out', function () {
    // Act
    $simulation = app(BalanceSimulator::class)->simulate(1, 3, TaskDifficulty::Medium, catalog());

    // Assert
    expect($simulation->catalogDay)->toBeNull()
        ->and($simulation->events)->toBe([]);
});
