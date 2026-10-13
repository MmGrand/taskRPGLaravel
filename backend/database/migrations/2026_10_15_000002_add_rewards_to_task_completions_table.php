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
        Schema::table('task_completions', function (Blueprint $table) {
            // What this completion gave, so undoing it takes back exactly that
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->unsignedInteger('gold_awarded')->default(0);
        });

        DB::statement('alter table task_completions add constraint task_completions_xp_awarded_non_negative check (xp_awarded >= 0)');
        DB::statement('alter table task_completions add constraint task_completions_gold_awarded_non_negative check (gold_awarded >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_completions', function (Blueprint $table) {
            $table->dropColumn(['xp_awarded', 'gold_awarded']);
        });
    }
};
