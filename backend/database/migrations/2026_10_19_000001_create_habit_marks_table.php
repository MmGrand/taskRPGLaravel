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
        Schema::create('habit_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            // The user's local calendar day the mark counts for; a habit takes many marks a day
            $table->date('period_date');
            $table->string('direction', 8);
            // What this mark gave, so undoing it takes back exactly this; a minus never gives anything
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->unsignedInteger('gold_awarded')->default(0);
            $table->timestampTz('created_at');

            $table->index(['task_id', 'period_date']);
        });

        // Blueprint has no check(), so guard the values here
        DB::statement("alter table habit_marks add constraint habit_marks_direction check (direction in ('up', 'down'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('habit_marks');
    }
};
