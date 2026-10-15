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
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('difficulty', 16)->default('medium');
            // ISO days as bits: 1 is Monday, 64 is Sunday, 127 is every day
            $table->unsignedSmallInteger('weekdays')->default(127);
            // Manual order inside the user's list; new tasks go last
            $table->unsignedInteger('position')->default(0);

            $table->index(['user_id', 'position']);
        });

        // Keeps the order users saw so far: creation order
        DB::statement('update tasks set position = id');
        // Postgres has no unsigned integers and Blueprint has no check(), so guard the range here
        DB::statement('alter table tasks add constraint tasks_weekdays_range check (weekdays between 1 and 127)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The check constraint is dropped together with its column
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'position']);
            $table->dropColumn(['difficulty', 'weekdays', 'position']);
        });
    }
};
