<?php

declare(strict_types=1);

namespace Tests\Feature\Database\ItemCatalogMigration;

use App\Enums\ItemSlot;
use App\Models\Character;
use App\Models\Item;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The namespace keeps this file's constants and helpers apart from those of other test files

// Kenney Roguelike Characters sheet size in tiles
const SHEET_COLUMNS = 54;
const SHEET_ROWS = 12;

uses(RefreshDatabase::class);

function catalogMigration(): Migration
{
    return require database_path('migrations/2026_10_26_000001_fill_item_catalog.php');
}

it('fills the catalog with one starter item per slot by migrations alone', function () {
    // Assert: nothing seeded, RefreshDatabase only ran the migrations
    expect(Item::query()->count())->toBe(37)
        ->and(Item::query()->get()->countBy(fn (Item $item) => $item->slot->value)->all())->toBe([
            'head' => 8,
            'body' => 9,
            'legs' => 6,
            'weapon' => 8,
            'shield' => 6,
        ])
        ->and(Item::query()->where('is_starter', true)->get()->map(fn (Item $item) => $item->slot)->all())
        ->toEqualCanonicalizing(ItemSlot::cases());
});

it('has one rare item per slot, free and never a starter', function () {
    // Act
    $rare = Item::query()->where('is_rare', true)->get();

    // Assert
    expect($rare->map(fn (Item $item) => $item->slot)->all())->toEqualCanonicalizing(ItemSlot::cases())
        ->and($rare->pluck('code')->all())->toEqualCanonicalizing([
            'valkyrie_helmet', 'amethyst_armor', 'paladin_pants', 'frost_axe', 'hero_shield',
        ])
        ->and($rare->every(fn (Item $item) => ! $item->is_starter && $item->price === 0))->toBeTrue();
});

it('gives every item a tile of its own', function () {
    // Act
    $tiles = Item::query()->get()->map(fn (Item $item) => "{$item->sprite_col}:{$item->sprite_row}");

    // Assert
    expect($tiles->duplicates()->all())->toBe([]);
});

it('keeps every sprite inside the sheet', function () {
    // Act & Assert
    Item::query()->each(function (Item $item) {
        expect($item->sprite_col)->toBeLessThan(SHEET_COLUMNS)
            ->and($item->sprite_row)->toBeLessThan(SHEET_ROWS);
    });
});

it('fills a database seeded before it without duplicates and restores changed items', function () {
    // Arrange: a database where the old seeder already put the catalog
    Item::query()->where('code', 'steel_sword')->update(['name' => 'Ржавый меч']);

    // Act
    catalogMigration()->up();

    // Assert
    expect(Item::query()->count())->toBe(37)
        ->and(Item::query()->where('code', 'steel_sword')->value('name'))->toBe('Стальной меч');
});

it('gives characters created before the catalog their starter kit once', function () {
    // Arrange
    Item::query()->delete();
    $character = Character::factory()->create();

    // Act
    catalogMigration()->up();
    catalogMigration()->up();

    // Assert
    expect($character->inventoryItems()->count())->toBe(5);
});
