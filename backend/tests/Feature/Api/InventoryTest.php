<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\User;
use App\Services\StarterKit;
use Database\Seeders\ItemCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ItemCatalogSeeder::class);
    $this->character = Character::factory()->create();
    app(StarterKit::class)->grantTo($this->character);
});

it('lists the items of the character', function () {
    // Act
    $response = $this->actingAs($this->character->user)->getJson('/api/character/inventory');

    // Assert
    $response->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.item.code', 'cloth_headband')
        ->assertJsonPath('data.0.item.slot', 'head')
        ->assertJsonPath('data.0.item.sprite', ['col' => 28, 'row' => 2])
        ->assertJsonStructure(['data' => [['item' => ['id', 'name', 'required_level', 'price'], 'acquired_at']]]);
});

it('does not list items of other characters', function () {
    // Arrange
    $other = Character::factory()->create();
    InventoryItem::factory()->for($other)->for(Item::query()->where('code', 'golden_sword')->sole())->create();

    // Act
    $response = $this->actingAs($this->character->user)->getJson('/api/character/inventory');

    // Assert
    expect(collect($response->json('data'))->pluck('item.code'))->not->toContain('golden_sword');
});

it('lists the inventory with a constant number of queries', function () {
    // Arrange
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->character->user)->getJson('/api/character/inventory')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    // Warm up: the first request also loads and caches the user's character relation
    $countQueries();
    $withFiveItems = $countQueries();

    // Act
    Item::query()->where('is_starter', false)->limit(5)->get()->each(
        fn (Item $item) => InventoryItem::factory()->for($this->character)->for($item)->create(),
    );
    $withTenItems = $countQueries();

    // Assert
    expect($withTenItems)->toBe($withFiveItems);
});

it('returns 404 when the user has no character yet', function () {
    // Act & Assert
    $this->actingAs(User::factory()->create())->getJson('/api/character/inventory')->assertNotFound();
});

it('requires authentication for the inventory', function () {
    // Act & Assert
    $this->getJson('/api/character/inventory')->assertUnauthorized();
});
