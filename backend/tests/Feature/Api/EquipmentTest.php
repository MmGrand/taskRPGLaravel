<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Equipment;

use App\Enums\ItemSlot;
use App\Exceptions\ItemDoesNotFitSlot;
use App\Models\Character;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\User;
use App\Services\EquipmentService;
use App\Services\StarterKit;
use Database\Seeders\ItemCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The namespace keeps this file's helper functions apart from those of other test files

uses(RefreshDatabase::class);

function item(string $code): Item
{
    return Item::query()->where('code', $code)->sole();
}

function equipmentUrl(string $slot): string
{
    return "/api/character/equipment/{$slot}";
}

beforeEach(function () {
    $this->seed(ItemCatalogSeeder::class);
    $this->character = Character::factory()->create();
    $this->user = $this->character->user;
    app(StarterKit::class)->grantTo($this->character);
});

it('equips an owned item into its slot', function () {
    // Act
    $response = $this->actingAs($this->user)->putJson(equipmentUrl('weapon'), ['item_id' => item('wooden_sword')->id]);

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.id', $this->character->id)
        ->assertJsonPath('data.equipment.0.slot', 'weapon')
        ->assertJsonPath('data.equipment.0.item.code', 'wooden_sword');
    $this->assertDatabaseCount('equipped_items', 1);
});

it('replaces the item worn in the same slot', function () {
    // Arrange
    InventoryItem::factory()->for($this->character)->for(item('green_pants'))->create();
    $this->actingAs($this->user)->putJson(equipmentUrl('legs'), ['item_id' => item('leather_pants')->id])->assertOk();

    // Act
    $response = $this->actingAs($this->user)->putJson(equipmentUrl('legs'), ['item_id' => item('green_pants')->id]);

    // Assert
    $response->assertOk()->assertJsonPath('data.equipment.0.item.code', 'green_pants');
    $this->assertDatabaseCount('equipped_items', 1);
    $this->assertDatabaseHas('equipped_items', ['character_id' => $this->character->id, 'item_id' => item('green_pants')->id]);
});

it('ignores equipping the same item twice', function () {
    // Arrange
    $payload = ['item_id' => item('teal_shirt')->id];
    $this->actingAs($this->user)->putJson(equipmentUrl('body'), $payload)->assertOk();

    // Act
    $response = $this->actingAs($this->user)->putJson(equipmentUrl('body'), $payload);

    // Assert
    $response->assertOk()->assertJsonCount(1, 'data.equipment');
    $this->assertDatabaseCount('equipped_items', 1);
});

it('wears one item per slot at the same time', function () {
    // Act
    $this->actingAs($this->user)->putJson(equipmentUrl('head'), ['item_id' => item('cloth_headband')->id])->assertOk();
    $response = $this->actingAs($this->user)->putJson(equipmentUrl('shield'), ['item_id' => item('wooden_buckler')->id]);

    // Assert
    expect(collect($response->json('data.equipment'))->pluck('slot')->sort()->values()->all())->toBe(['head', 'shield']);
});

it('rejects items that cannot be equipped', function (string $slot, string $code) {
    // Act
    $response = $this->actingAs($this->user)->putJson(equipmentUrl($slot), ['item_id' => item($code)->id]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['item_id']);
    $this->assertDatabaseCount('equipped_items', 0);
})->with([
    'item made for another slot' => ['head', 'teal_shirt'],
    'item the character does not own' => ['weapon', 'steel_sword'],
]);

it('rejects an item owned by another character', function () {
    // Arrange
    $other = Character::factory()->create();
    InventoryItem::factory()->for($other)->for(item('golden_helmet'))->create();

    // Act
    $response = $this->actingAs($this->user)->putJson(equipmentUrl('head'), ['item_id' => item('golden_helmet')->id]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['item_id']);
});

it('validates the item id', function (array $payload) {
    // Act
    $response = $this->actingAs($this->user)->putJson(equipmentUrl('weapon'), $payload);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['item_id']);
})->with([
    'missing' => [[]],
    'not a number' => [['item_id' => 'sword']],
    'unknown item' => [['item_id' => 999999]],
]);

it('explains validation errors in Russian', function () {
    // Act
    $response = $this->actingAs($this->user)->putJson(equipmentUrl('weapon'), []);

    // Assert
    $response->assertJsonValidationErrors(['item_id' => 'Поле предмет обязательно.']);
});

it('returns 404 for an unknown slot', function () {
    // Act & Assert
    $this->actingAs($this->user)->putJson(equipmentUrl('feet'), ['item_id' => item('wooden_sword')->id])->assertNotFound();
    $this->actingAs($this->user)->deleteJson(equipmentUrl('feet'))->assertNotFound();
});

it('takes an item off', function () {
    // Arrange
    $this->actingAs($this->user)->putJson(equipmentUrl('weapon'), ['item_id' => item('wooden_sword')->id])->assertOk();

    // Act
    $response = $this->actingAs($this->user)->deleteJson(equipmentUrl('weapon'));

    // Assert
    $response->assertOk()->assertJsonPath('data.equipment', []);
    $this->assertDatabaseCount('equipped_items', 0);
});

it('treats taking off an empty slot as a no-op', function () {
    // Act & Assert
    $this->actingAs($this->user)->deleteJson(equipmentUrl('shield'))->assertOk()->assertJsonPath('data.equipment', []);
});

it('returns 404 when the user has no character yet', function () {
    // Arrange
    $newcomer = User::factory()->create();

    // Act & Assert
    $this->actingAs($newcomer)->putJson(equipmentUrl('weapon'), ['item_id' => item('wooden_sword')->id])->assertNotFound();
    $this->actingAs($newcomer)->deleteJson(equipmentUrl('weapon'))->assertNotFound();
});

it('requires authentication for equipment endpoints', function () {
    // Act & Assert
    $this->putJson(equipmentUrl('weapon'), ['item_id' => item('wooden_sword')->id])->assertUnauthorized();
    $this->deleteJson(equipmentUrl('weapon'))->assertUnauthorized();
});

it('refuses in the service to wear an item made for another slot', function () {
    // Act & Assert
    expect(fn () => app(EquipmentService::class)->equip($this->character, ItemSlot::Head, item('teal_shirt')))
        ->toThrow(ItemDoesNotFitSlot::class);
    $this->assertDatabaseCount('equipped_items', 0);
});

it('refuses at the database level to wear an owned item in another slot', function () {
    // Act & Assert: the character owns the shirt, but a shirt is not headgear
    expect(fn () => $this->character->equippedItems()->create([
        'slot' => 'head',
        'item_id' => item('teal_shirt')->id,
        'equipped_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('refuses at the database level to wear an item that is not owned', function () {
    // Act & Assert
    expect(fn () => $this->character->equippedItems()->create([
        'slot' => 'weapon',
        'item_id' => item('diamond_sword')->id,
        'equipped_at' => now(),
    ]))->toThrow(QueryException::class);
});
