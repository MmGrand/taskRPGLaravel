<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('equipped_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('slot', 16);
            $table->unsignedBigInteger('item_id');
            $table->timestampTz('equipped_at');

            $table->unique(['character_id', 'slot']);
            // Only an owned item can be worn: the pair must exist in the inventory
            $table->foreign(['character_id', 'item_id'])
                ->references(['character_id', 'item_id'])
                ->on('inventory_items')
                ->cascadeOnDelete();
            // And only in the slot it was made for
            $table->foreign(['item_id', 'slot'])
                ->references(['id', 'slot'])
                ->on('items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipped_items');
    }
};
