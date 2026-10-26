<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Shop;

use App\Models\Character;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\User;
use App\Services\StarterKit;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The namespace keeps this file's helper functions apart from those of other test files

uses(RefreshDatabase::class);

const LEVEL_TWO_XP = 50;

function item(string $code): Item
{
    return Item::query()->where('code', $code)->sole();
}

/**
 * @param  list<array<string, mixed>>  $catalog
 * @return array<string, mixed>
 */
function entry(array $catalog, string $code): array
{
    return collect($catalog)->firstWhere('item.code', $code);
}

beforeEach(function () {
    $this->character = Character::factory()->create();
    $this->user = $this->character->user;
    app(StarterKit::class)->grantTo($this->character);
});

it('lists the whole catalog ordered by level', function () {
    // Act
    $response = $this->actingAs($this->user)->getJson('/api/shop');

    // Assert
    $response->assertOk()->assertJsonCount(32, 'data');
    $levels = collect($response->json('data'))->pluck('item.required_level')->all();
    expect($levels)->toBe(collect($levels)->sort()->values()->all());
});

it('leaves rare items out of the shop', function () {
    // Act
    $catalog = $this->actingAs($this->user)->getJson('/api/shop')->json('data');

    // Assert
    expect(collect($catalog)->pluck('item.is_rare')->unique()->all())->toBe([false])
        ->and(collect($catalog)->pluck('item.code'))->not->toContain('frost_axe');
});

it('never reports a rare item as new in the shop', function () {
    // Arrange: a rare item of level 2 would otherwise be unlocked by this level
    Item::factory()->rare()->create(['code' => 'rare_of_level_two', 'required_level' => 2]);
    Character::query()->whereKey($this->character->id)->update(['xp' => LEVEL_TWO_XP]);

    // Act
    $response = $this->actingAs($this->user)->getJson('/api/character');

    // Assert
    expect(collect($response->json('data.shop.new_items'))->pluck('code'))
        ->toContain('green_bandana')
        ->not->toContain('rare_of_level_two');
});

it('marks owned items and locks items above the level', function () {
    // Act
    $catalog = $this->actingAs($this->user)->getJson('/api/shop')->json('data');

    // Assert
    expect(entry($catalog, 'cloth_headband'))->owned->toBeTrue()->unlocked->toBeTrue()
        ->and(entry($catalog, 'green_bandana'))->owned->toBeFalse()->unlocked->toBeFalse();
});

it('unlocks items once the hero reaches their level', function () {
    // Arrange
    Character::query()->whereKey($this->character->id)->update(['xp' => LEVEL_TWO_XP]);

    // Act
    $catalog = $this->actingAs($this->user)->getJson('/api/shop')->json('data');

    // Assert
    expect(entry($catalog, 'green_bandana')['unlocked'])->toBeTrue()
        ->and(entry($catalog, 'feathered_hat')['unlocked'])->toBeFalse();
});

it('ignores what other heroes own', function () {
    // Arrange
    InventoryItem::factory()->for(Character::factory()->create())->for(item('golden_sword'))->create();

    // Act
    $catalog = $this->actingAs($this->user)->getJson('/api/shop')->json('data');

    // Assert
    expect(entry($catalog, 'golden_sword')['owned'])->toBeFalse();
});

it('reports no new items while nothing is unlocked beyond the starter kit', function () {
    // Act
    $response = $this->actingAs($this->user)->getJson('/api/character');

    // Assert
    $response->assertOk()->assertJsonPath('data.shop.new_items', []);
});

it('reports the items a new level unlocked until the shop is opened', function () {
    // Arrange
    Character::query()->whereKey($this->character->id)->update(['xp' => LEVEL_TWO_XP]);

    // Act
    $response = $this->actingAs($this->user)->getJson('/api/character');

    // Assert
    expect(collect($response->json('data.shop.new_items'))->pluck('code')->all())
        ->toBe(['green_bandana', 'hunter_vest', 'green_pants', 'apprentice_staff', 'round_shield']);
});

it('does not report a new item the hero already owns', function () {
    // Arrange
    Character::query()->whereKey($this->character->id)->update(['xp' => LEVEL_TWO_XP]);
    InventoryItem::factory()->for($this->character)->for(item('green_bandana'))->create();

    // Act
    $codes = collect($this->actingAs($this->user)->getJson('/api/character')->json('data.shop.new_items'))->pluck('code');

    // Assert
    expect($codes)->not->toContain('green_bandana')->toHaveCount(4);
});

it('clears the new items when the shop is opened', function () {
    // Arrange
    Character::query()->whereKey($this->character->id)->update(['xp' => LEVEL_TWO_XP]);

    // Act
    $response = $this->actingAs($this->user)->putJson('/api/character/shop-seen');

    // Assert
    $response->assertOk()->assertJsonPath('data.shop.new_items', []);
    expect($this->character->fresh()->shop_seen_level)->toBe(2);
});

it('keeps the highest seen level when the hero loses a level', function () {
    // Arrange: seen at level 2, then the XP is taken back and the shop is opened again
    Character::query()->whereKey($this->character->id)->update(['xp' => LEVEL_TWO_XP]);
    $this->actingAs($this->user)->putJson('/api/character/shop-seen')->assertOk();
    Character::query()->whereKey($this->character->id)->update(['xp' => 0]);
    $this->actingAs($this->user)->putJson('/api/character/shop-seen')->assertOk();

    // Act: level 2 again
    Character::query()->whereKey($this->character->id)->update(['xp' => LEVEL_TWO_XP]);
    $response = $this->actingAs($this->user)->getJson('/api/character');

    // Assert
    $response->assertJsonPath('data.shop.new_items', []);
    expect($this->character->fresh()->shop_seen_level)->toBe(2);
});

it('answers 404 without a character', function () {
    // Arrange
    $user = User::factory()->create();

    // Act & Assert
    $this->actingAs($user)->getJson('/api/shop')->assertNotFound();
    $this->actingAs($user)->putJson('/api/character/shop-seen')->assertNotFound();
});

it('requires authentication for the shop', function () {
    // Act & Assert
    $this->getJson('/api/shop')->assertUnauthorized();
    $this->putJson('/api/character/shop-seen')->assertUnauthorized();
});
