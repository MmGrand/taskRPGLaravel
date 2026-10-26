<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use Illuminate\Support\Facades\DB;

final class StarterKit
{
    // Idempotent: items a character already owns are skipped by the unique index
    public function grantTo(Character $character): void
    {
        DB::table('inventory_items')->insertOrIgnoreUsing(
            ['character_id', 'item_id', 'acquired_at'],
            Character::query()->whereKey($character->id)->toBase()
                ->crossJoin('items')
                ->where('items.is_starter', true)
                ->select(['characters.id', 'items.id'])
                // The application clock, not the database one, so time travel in tests applies too
                ->selectRaw('?::timestamptz', [now()]),
        );
    }
}
