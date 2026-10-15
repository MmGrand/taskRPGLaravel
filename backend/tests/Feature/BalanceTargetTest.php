<?php

declare(strict_types=1);

namespace Tests\Feature\BalanceTarget;

// The namespace keeps this file's constants apart from those of other test files

use App\Enums\TaskDifficulty;
use App\Models\Item;
use App\Services\BalanceSimulator;
use Database\Seeders\ItemCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The target the balance was tuned to (phase 12): a player with five medium tasks a day
// reaches the top level and buys the whole catalog in about six to seven weeks
const TARGET_FIRST_DAY = 38;
const TARGET_LAST_DAY = 50;
// Level 5 should still take a week, or the early game is over before it starts
const EARLIEST_LEVEL_FIVE = 7;
const TOP_LEVEL = 10;

it('keeps the real balance on target', function () {
    // Arrange
    $this->useRealBalance();
    $this->seed(ItemCatalogSeeder::class);

    // Act
    $simulation = app(BalanceSimulator::class)->simulate(5, 90, TaskDifficulty::Medium, Item::query()->get());

    // Assert
    expect($simulation->levelDays[TOP_LEVEL])->toBeGreaterThanOrEqual(TARGET_FIRST_DAY)->toBeLessThanOrEqual(TARGET_LAST_DAY)
        ->and($simulation->catalogDay)->toBeGreaterThanOrEqual(TARGET_FIRST_DAY)->toBeLessThanOrEqual(TARGET_LAST_DAY)
        ->and($simulation->levelDays[5])->toBeGreaterThanOrEqual(EARLIEST_LEVEL_FIVE);
});
