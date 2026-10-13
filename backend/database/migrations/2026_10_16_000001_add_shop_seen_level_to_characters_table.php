<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // The highest level at which the player opened the shop; items unlocked above it are new
            $table->unsignedSmallInteger('shop_seen_level')->default(1);
        });

        // Postgres has no unsigned integers and Blueprint has no check(), so guard the range here
        DB::statement('alter table characters add constraint characters_shop_seen_level_positive check (shop_seen_level >= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The check constraint is dropped together with its column
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('shop_seen_level');
        });
    }
};
