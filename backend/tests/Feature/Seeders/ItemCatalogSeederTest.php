<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders\ItemCatalog;

use App\Enums\ItemSlot;
use App\Models\Character;
use App\Models\Item;
use App\Services\StarterKit;
use Carbon\CarbonImmutable;
use Database\Seeders\ItemCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The namespace keeps this file's constants apart from those of other test files

// Kenney Roguelike Characters sheet size in tiles
const SHEET_COLUMNS = 54;
const SHEET_ROWS = 12;

uses(RefreshDatabase::class);

it('seeds the catalog with one starter item per slot', function () {
    // Act
    $this->seed(ItemCatalogSeeder::class);

    // Assert
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

it('seeds one rare item per slot, free and never a starter', function () {
    // Act
    $this->seed(ItemCatalogSeeder::class);

    // Assert
    $rare = Item::query()->where('is_rare', true)->get();
    expect($rare->map(fn (Item $item) => $item->slot)->all())->toEqualCanonicalizing(ItemSlot::cases())
        ->and($rare->pluck('code')->all())->toEqualCanonicalizing([
            'valkyrie_helmet', 'amethyst_armor', 'paladin_pants', 'frost_axe', 'hero_shield',
        ])
        ->and($rare->every(fn (Item $item) => ! $item->is_starter && $item->price === 0))->toBeTrue();
});

it('gives every item a tile of its own', function () {
    // Act
    $this->seed(ItemCatalogSeeder::class);

    // Assert
    $tiles = Item::query()->get()->map(fn (Item $item) => "{$item->sprite_col}:{$item->sprite_row}");
    expect($tiles->duplicates()->all())->toBe([]);
});

it('keeps every sprite inside the sheet', function () {
    // Act
    $this->seed(ItemCatalogSeeder::class);

    // Assert
    Item::query()->each(function (Item $item) {
        expect($item->sprite_col)->toBeLessThan(SHEET_COLUMNS)
            ->and($item->sprite_row)->toBeLessThan(SHEET_ROWS);
    });
});

it('can run again without duplicates and restores changed items', function () {
    // Arrange
    $this->seed(ItemCatalogSeeder::class);
    Item::query()->where('code', 'steel_sword')->update(['name' => 'Ржавый меч']);

    // Act
    $this->seed(ItemCatalogSeeder::class);

    // Assert
    expect(Item::query()->count())->toBe(37)
        ->and(Item::query()->where('code', 'steel_sword')->value('name'))->toBe('Стальной меч');
});

it('gives existing characters the starter kit once', function () {
    // Arrange
    $character = Character::factory()->create();

    // Act
    $this->seed(ItemCatalogSeeder::class);
    $this->seed(ItemCatalogSeeder::class);

    // Assert
    expect($character->inventoryItems()->count())->toBe(5);
});

it('stamps starter items with the application clock', function () {
    // Arrange
    $this->travelTo(CarbonImmutable::parse('2026-01-02 03:04:05', 'UTC'));
    $this->seed(ItemCatalogSeeder::class);
    $hero = Character::factory()->create();

    // Act
    app(StarterKit::class)->grantTo($hero);

    // Assert
    expect($hero->inventoryItems()->pluck('acquired_at')->map->toIso8601String()->unique()->all())
        ->toBe(['2026-01-02T03:04:05+00:00']);
});

it('grants the starter kit only to the given character', function () {
    // Arrange
    $this->seed(ItemCatalogSeeder::class);
    $hero = Character::factory()->create();
    $other = Character::factory()->create();

    // Act
    app(StarterKit::class)->grantTo($hero);

    // Assert
    expect($hero->inventoryItems()->count())->toBe(5)
        ->and($other->inventoryItems()->count())->toBe(0);
});
