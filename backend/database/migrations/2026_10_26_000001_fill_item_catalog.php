<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The catalog is reference data the game cannot run without, so migrations put it in every
// environment. The rows are written out here, not read from the code: a later change to the
// catalog is a new migration, and this one keeps working on a fresh database. Columns added
// to items later need a default so these rows still insert.
return new class extends Migration
{
    // code, name, slot, sprite col, sprite row, required level, price, starter.
    // Every tile was checked on a character: headgear sits over the hair,
    // shields in the left hand, weapons in the right.
    private const array CATALOG = [
        ['cloth_headband', 'Повязка', 'head', 28, 2, 1, 0, true],
        ['green_bandana', 'Зелёная бандана', 'head', 30, 5, 2, 30, false],
        ['feathered_hat', 'Шляпа с пером', 'head', 29, 7, 3, 60, false],
        ['wizard_hat', 'Шляпа мага', 'head', 30, 8, 4, 90, false],
        ['steel_helmet', 'Стальной шлем', 'head', 28, 0, 5, 130, false],
        ['horned_helmet', 'Рогатый шлем', 'head', 28, 6, 7, 180, false],
        ['golden_helmet', 'Золотой шлем', 'head', 30, 1, 9, 260, false],
        ['teal_shirt', 'Бирюзовая рубаха', 'body', 10, 0, 1, 0, true],
        ['hunter_vest', 'Жилет охотника', 'body', 9, 5, 2, 40, false],
        ['leather_armor', 'Кожаный доспех', 'body', 6, 4, 3, 70, false],
        ['teal_robe', 'Бирюзовая мантия', 'body', 10, 2, 4, 100, false],
        ['wizard_robe', 'Мантия мага', 'body', 14, 2, 5, 130, false],
        ['steel_armor', 'Стальной доспех', 'body', 10, 4, 6, 170, false],
        ['shadow_robe', 'Мантия теней', 'body', 14, 7, 8, 230, false],
        ['golden_armor', 'Золотой доспех', 'body', 10, 9, 10, 320, false],
        ['leather_pants', 'Кожаные штаны', 'legs', 3, 1, 1, 0, true],
        ['green_pants', 'Зелёные штаны', 'legs', 3, 8, 2, 30, false],
        ['teal_pants', 'Бирюзовые штаны', 'legs', 3, 6, 3, 50, false],
        ['lilac_pants', 'Сиреневые штаны', 'legs', 3, 7, 5, 90, false],
        ['linen_pants', 'Льняные штаны', 'legs', 3, 3, 7, 130, false],
        ['wooden_sword', 'Деревянный меч', 'weapon', 42, 6, 1, 0, true],
        ['apprentice_staff', 'Посох ученика', 'weapon', 42, 0, 2, 30, false],
        ['hunting_bow', 'Охотничий лук', 'weapon', 52, 0, 3, 60, false],
        ['steel_sword', 'Стальной меч', 'weapon', 44, 6, 4, 90, false],
        ['battle_axe', 'Боевой топор', 'weapon', 47, 1, 5, 120, false],
        ['golden_sword', 'Золотой меч', 'weapon', 45, 6, 8, 200, false],
        ['diamond_sword', 'Алмазный меч', 'weapon', 46, 6, 10, 300, false],
        ['wooden_buckler', 'Деревянный баклер', 'shield', 33, 0, 1, 0, true],
        ['round_shield', 'Круглый щит', 'shield', 33, 1, 2, 40, false],
        ['steel_shield', 'Стальной щит', 'shield', 37, 1, 4, 100, false],
        ['tower_shield', 'Ростовой щит', 'shield', 36, 0, 6, 150, false],
        ['knight_shield', 'Рыцарский щит', 'shield', 38, 4, 8, 220, false],
    ];

    // code, name, slot, sprite col, sprite row. One per slot, given for an achievement
    // (App\Achievements\AchievementCatalog) and never sold, so they need no level and no price.
    // Tiles the shop catalog does not use, checked on a character like the ones above.
    private const array RARE = [
        ['valkyrie_helmet', 'Шлем валькирии', 'head', 30, 7],
        ['amethyst_armor', 'Аметистовый доспех', 'body', 16, 4],
        ['paladin_pants', 'Штаны паладина', 'legs', 3, 2],
        ['frost_axe', 'Морозный топор', 'weapon', 49, 8],
        ['hero_shield', 'Щит героя', 'shield', 37, 3],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $row = fn (array $item, int $level, int $price, bool $starter, bool $rare): array => [
            'code' => $item[0],
            'name' => $item[1],
            'slot' => $item[2],
            'sprite_col' => $item[3],
            'sprite_row' => $item[4],
            'required_level' => $level,
            'price' => $price,
            'is_starter' => $starter,
            'is_rare' => $rare,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // An upsert, since databases set up before this migration already got the catalog from a seeder
        DB::table('items')->upsert(
            [
                ...array_map(fn (array $item): array => $row($item, $item[5], $item[6], $item[7], false), self::CATALOG),
                ...array_map(fn (array $item): array => $row($item, 1, 0, false, true), self::RARE),
            ],
            ['code'],
            ['name', 'slot', 'sprite_col', 'sprite_row', 'required_level', 'price', 'is_starter', 'is_rare', 'updated_at'],
        );

        // Characters created while the catalog was empty get their starter kit too.
        // Plain queries rather than App\Services\StarterKit, so later changes to the service
        // cannot break this migration
        DB::table('inventory_items')->insertOrIgnoreUsing(
            ['character_id', 'item_id', 'acquired_at'],
            DB::table('characters')
                ->crossJoin('items')
                ->where('items.is_starter', true)
                ->select(['characters.id', 'items.id'])
                ->selectRaw('?::timestamptz', [$now]),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Left in place: characters own and wear these items, and deleting them would take
        // purchases with them. Running up() again is safe, it updates the rows in place
    }
};
