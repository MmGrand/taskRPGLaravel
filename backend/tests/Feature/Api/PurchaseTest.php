<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Purchase;

use App\Exceptions\PurchaseRefused;
use App\Models\Character;
use App\Models\Item;
use App\Models\User;
use App\Services\ShopService;
use App\Services\StarterKit;
use Carbon\CarbonImmutable;
use Database\Seeders\ItemCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The namespace keeps this file's helper functions apart from those of other test files

uses(RefreshDatabase::class);

const LEVEL_TWO_XP = 50;

function item(string $code): Item
{
    return Item::query()->where('code', $code)->sole();
}

function purchase(Item $item): array
{
    return ['item_id' => $item->id];
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->seed(ItemCatalogSeeder::class);
    $this->character = Character::factory()->withXp(LEVEL_TWO_XP, 40)->create();
    $this->user = $this->character->user;
    app(StarterKit::class)->grantTo($this->character);
});

it('buys an unlocked item for gold', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/shop/purchases', purchase(item('green_bandana')));

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.id', $this->character->id)
        ->assertJsonPath('data.gold', 10);
    $this->assertDatabaseHas('inventory_items', [
        'character_id' => $this->character->id,
        'item_id' => item('green_bandana')->id,
        'acquired_at' => '2026-10-13 12:00:00+00',
    ]);
});

it('spends the last gold on an item that costs exactly that', function () {
    // Arrange
    Character::query()->whereKey($this->character->id)->update(['gold' => 30]);

    // Act
    $response = $this->actingAs($this->user)->postJson('/api/shop/purchases', purchase(item('green_bandana')));

    // Assert
    $response->assertCreated()->assertJsonPath('data.gold', 0);
});

it('refuses to buy an item twice', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/shop/purchases', purchase(item('cloth_headband')));

    // Assert
    $response->assertUnprocessable()->assertJsonPath('errors.item_id.0', 'Этот предмет уже куплен.');
    expect($this->character->fresh()->gold)->toBe(40);
});

it('refuses an item above the level of the hero', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/shop/purchases', purchase(item('golden_helmet')));

    // Assert
    $response->assertUnprocessable()->assertJsonPath('errors.item_id.0', 'Предмет откроется на уровне 9.');
    $this->assertDatabaseMissing('inventory_items', ['item_id' => item('golden_helmet')->id]);
});

it('refuses to sell a rare item', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/shop/purchases', purchase(item('frost_axe')));

    // Assert
    $response->assertUnprocessable()->assertJsonPath('errors.item_id.0', 'Этот предмет не продаётся: его дают за достижение.');
    $this->assertDatabaseMissing('inventory_items', ['item_id' => item('frost_axe')->id]);
});

it('refuses an item the hero cannot afford', function () {
    // Arrange
    Character::query()->whereKey($this->character->id)->update(['gold' => 10]);

    // Act
    $response = $this->actingAs($this->user)->postJson('/api/shop/purchases', purchase(item('green_bandana')));

    // Assert
    $response->assertUnprocessable()->assertJsonPath('errors.item_id.0', 'Не хватает золота: нужно 30, есть 10.');
    expect($this->character->fresh()->gold)->toBe(10);
});

it('rejects an invalid item id', function (array $payload) {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/shop/purchases', $payload);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['item_id']);
})->with([
    'missing' => [[]],
    'not a number' => [['item_id' => 'sword']],
    'unknown' => [['item_id' => 999_999]],
]);

it('leaves the gold untouched when the service refuses', function () {
    // Arrange
    Character::query()->whereKey($this->character->id)->update(['gold' => 10]);

    // Act & Assert
    expect(fn () => app(ShopService::class)->buy($this->character, item('green_bandana')))
        ->toThrow(PurchaseRefused::class);
    expect($this->character->fresh()->gold)->toBe(10);
});

it('never lets the database store negative gold', function () {
    // Act & Assert
    expect(fn () => Character::query()->whereKey($this->character->id)->update(['gold' => -1]))
        ->toThrow(QueryException::class);
});

it('lets the hero wear what was bought, even after losing the level', function () {
    // Arrange
    $this->actingAs($this->user)->postJson('/api/shop/purchases', purchase(item('green_bandana')))->assertCreated();
    Character::query()->whereKey($this->character->id)->update(['xp' => 0]);

    // Act
    $response = $this->actingAs($this->user)->putJson('/api/character/equipment/head', purchase(item('green_bandana')));

    // Assert
    $response->assertOk()->assertJsonPath('data.equipment.0.item.code', 'green_bandana');
});

it('answers 404 without a character', function () {
    // Act & Assert
    $this->actingAs(User::factory()->create())
        ->postJson('/api/shop/purchases', purchase(item('green_bandana')))
        ->assertNotFound();
});

it('requires authentication to buy', function () {
    // Act & Assert
    $this->postJson('/api/shop/purchases', purchase(item('green_bandana')))->assertUnauthorized();
});
