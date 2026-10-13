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
            // Total XP ever earned; the level is derived from it by ProgressionService
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedInteger('gold')->default(0);
        });

        // Postgres has no unsigned integers and Blueprint has no check(), so guard the sign here
        DB::statement('alter table characters add constraint characters_xp_non_negative check (xp >= 0)');
        DB::statement('alter table characters add constraint characters_gold_non_negative check (gold >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The check constraints are dropped together with their columns
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['xp', 'gold']);
        });
    }
};
