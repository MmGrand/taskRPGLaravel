<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders\Demo;

use App\Models\Item;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use App\Services\StatsService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ItemCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// The namespace keeps this file's helpers apart from those of other test files

uses(RefreshDatabase::class);

// Totals of the 12-day demo history and the five purchases, on the real balance
const DEMO_XP = 614;
const DEMO_GOLD = 239;
// What one completion gives today: day 13 of the streak, bonus capped at +100%
const TODAY_XP = 20;
const TODAY_GOLD = 16;

function seedDemo(TestCase $test): User
{
    $test->seed(ItemCatalogSeeder::class);
    $test->seed(DemoSeeder::class);

    return User::query()->where('email', DemoSeeder::EMAIL)->sole();
}

beforeEach(function () {
    // The demo is what reviewers see, so it is checked on the numbers players get
    $this->useRealBalance();
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
});

it('creates a level 4 hero two completions short of level 5', function () {
    // Arrange
    $demo = seedDemo($this);

    // Act
    $character = $this->actingAs($demo)->getJson('/api/user')->assertOk()->json('data.character');

    // Assert
    $left = $character['progress']['xp_to_next_level'] - $character['progress']['xp_in_level'];
    expect($character['progress']['level'])->toBe(4)
        ->and($left)->toBeGreaterThan(TODAY_XP)->toBeLessThanOrEqual(2 * TODAY_XP)
        ->and($character['streak'])->toBe(['days' => 12, 'active_today' => false]);
});

it('keeps the totals equal to the history and the purchases', function () {
    // Arrange
    $demo = seedDemo($this);
    $character = $demo->character;

    // Act
    $earned = TaskCompletion::query()->selectRaw('sum(xp_awarded) as xp, sum(gold_awarded) as gold')->first();
    $spent = Item::query()
        ->whereIn('id', $character->inventoryItems()->select('item_id'))
        ->sum('price');

    // Assert
    expect($character->xp)->toBe(DEMO_XP)->toBe((int) $earned->xp)
        ->and($character->gold)->toBe(DEMO_GOLD)->toBe((int) $earned->gold - (int) $spent);
});

it('wears the bought items and leaves today open', function () {
    // Arrange
    $demo = seedDemo($this);

    // Act
    $character = $this->actingAs($demo)->getJson('/api/user')->json('data.character');
    $tasks = collect($this->actingAs($demo)->getJson('/api/tasks')->assertOk()->json('data'));

    // Assert
    expect(collect($character['equipment'])->pluck('item.code')->all())->toEqualCanonicalizing([
        'green_bandana', 'leather_armor', 'leather_pants', 'wooden_sword', 'wooden_buckler',
    ])
        ->and($character['shop']['new_items'])->toBe([])
        ->and($tasks->where('type', 'daily'))->toHaveCount(5)
        ->and($tasks->where('type', 'todo'))->toHaveCount(3)
        ->and($tasks->where('is_completed', true))->toBeEmpty();
});

it('levels up after two completions and can buy a new item', function () {
    // Arrange
    $demo = seedDemo($this);
    $dailies = $demo->tasks()->where('type', 'daily')->orderBy('id')->take(2)->get();

    // Act
    $this->actingAs($demo)->putJson("/api/tasks/{$dailies[0]->id}/completion")->assertOk();
    $second = $this->actingAs($demo)->putJson("/api/tasks/{$dailies[1]->id}/completion")->assertOk();
    $purchase = $this->actingAs($demo)->postJson('/api/shop/purchases', [
        'item_id' => Item::query()->where('code', 'steel_helmet')->value('id'),
    ]);

    // Assert
    expect($second->json('character.progress.level'))->toBe(5)
        ->and($second->json('reward'))->toBe(['xp' => TODAY_XP, 'gold' => TODAY_GOLD, 'capped' => false]);
    $purchase->assertCreated();
});

it('counts days on the calendar of the demo user', function () {
    // Arrange: late evening in UTC is already the next day in Moscow
    $this->travelTo(CarbonImmutable::parse('2026-10-13 22:30:00', 'UTC'));
    $demo = seedDemo($this);

    // Act
    $character = $this->actingAs($demo)->getJson('/api/user')->json('data.character');
    $tasks = collect($this->actingAs($demo)->getJson('/api/tasks')->json('data'));

    // Assert
    expect($character['streak'])->toBe(['days' => 12, 'active_today' => false])
        ->and($tasks->where('is_completed', true))->toBeEmpty()
        ->and(TaskCompletion::query()->max('period_date'))->toStartWith('2026-10-13');
});

it('can run again without touching the demo account', function () {
    // Arrange
    $demo = seedDemo($this);
    $daily = $demo->tasks()->where('type', 'daily')->firstOrFail();
    $this->actingAs($demo)->putJson("/api/tasks/{$daily->id}/completion")->assertOk();

    // Act
    $this->seed(DemoSeeder::class);

    // Assert
    expect(User::query()->where('email', DemoSeeder::EMAIL)->count())->toBe(1)
        ->and($demo->character->refresh()->xp)->toBe(DEMO_XP + TODAY_XP);
});

it('moves an aged history so its last day is yesterday', function () {
    // Arrange: seeded on the 13th, the container starts again a week later
    $demo = seedDemo($this);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00', 'UTC'));

    // Act
    $this->seed(DemoSeeder::class);

    // Assert
    $character = $this->actingAs($demo)->getJson('/api/user')->json('data.character');
    expect($character['streak'])->toBe(['days' => 12, 'active_today' => false])
        ->and($character['progress']['xp_total'])->toBe(DEMO_XP)
        ->and(TaskCompletion::query()->max('period_date'))->toStartWith('2026-10-19')
        ->and(TaskCompletion::query()->min('period_date'))->toStartWith('2026-10-08');
});

it('keeps what the reviewer did and moves it along', function () {
    // Arrange: a completion on the 13th, then five days without a start
    $demo = seedDemo($this);
    $daily = $demo->tasks()->where('type', 'daily')->orderBy('id')->firstOrFail();
    $this->actingAs($demo)->putJson("/api/tasks/{$daily->id}/completion")->assertOk();
    $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00:00', 'UTC'));

    // Act
    $this->seed(DemoSeeder::class);

    // Assert
    $character = $this->actingAs($demo)->getJson('/api/user')->json('data.character');
    expect($character['streak'])->toBe(['days' => 13, 'active_today' => false])
        ->and($character['progress']['xp_total'])->toBe(DEMO_XP + TODAY_XP)
        ->and($daily->completions()->max('period_date'))->toStartWith('2026-10-17');
});

it('never moves a task or a purchase made after the last completion into the future', function () {
    // Arrange: two days after the last completion the reviewer adds a task and buys an item, then a long pause
    $demo = seedDemo($this);
    $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00', 'UTC'));
    $task = $this->actingAs($demo)->postJson('/api/tasks', ['title' => 'Новая', 'type' => 'daily'])->json('data.id');
    $this->actingAs($demo)->postJson('/api/shop/purchases', [
        'item_id' => Item::query()->where('code', 'apprentice_staff')->value('id'),
    ])->assertCreated();
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00', 'UTC'));

    // Act: the history moves 7 days, the new task and item would move to the 21st
    $this->seed(DemoSeeder::class);

    // Assert
    expect(Task::query()->findOrFail($task)->created_at->lessThanOrEqualTo(now()))->toBeTrue()
        ->and($demo->character->inventoryItems()->max('acquired_at'))->toStartWith('2026-10-20 12:00:00');
});

it('leaves a history that ended yesterday or today where it is', function (string $now) {
    // Arrange
    seedDemo($this);
    $this->travelTo(CarbonImmutable::parse($now, 'UTC'));

    // Act
    $this->seed(DemoSeeder::class);

    // Assert
    expect(TaskCompletion::query()->max('period_date'))->toStartWith('2026-10-12');
})->with(['same day' => '2026-10-13 18:00:00', 'next morning in Moscow' => '2026-10-12 23:00:00']);

it('gives the stats page a share of the demo dailies', function () {
    // Arrange
    $demo = seedDemo($this);

    // Act
    $rate = app(StatsService::class)->dailyCompletionRate($demo);

    // Assert: five dailies over twelve days, done 3 or 4 a day
    expect($rate->scheduled)->toBe(60)
        ->and($rate->completed)->toBe(39)
        ->and($rate->percent())->toBe(65);
});

it('never creates the account with a published password outside local development', function () {
    // Arrange
    $this->seed(ItemCatalogSeeder::class);
    $this->app->detectEnvironment(fn (): string => 'production');

    // Act: directly, since db:seed would ask to confirm running in production
    $this->app->make(DemoSeeder::class)->run();

    // Assert
    expect(User::query()->where('email', DemoSeeder::EMAIL)->exists())->toBeFalse();
});

it('lets the reviewer sign in with the documented password', function () {
    // Act
    $demo = seedDemo($this);

    // Assert
    expect(Hash::check(DemoSeeder::PASSWORD, $demo->password))->toBeTrue();
});

it('seeds the catalog and the demo from the default seeder', function () {
    // Act
    $this->seed();

    // Assert
    expect(Item::query()->count())->toBe(32)
        ->and(User::query()->where('email', DemoSeeder::EMAIL)->exists())->toBeTrue();
});
