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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            // Stable key for the seeder and future content updates
            $table->string('code', 48)->unique();
            $table->string('name', 48);
            $table->string('slot', 16);
            // Tile position on the Kenney Roguelike Characters sheet
            $table->unsignedSmallInteger('sprite_col');
            $table->unsignedSmallInteger('sprite_row');
            $table->unsignedSmallInteger('required_level')->default(1);
            $table->unsignedInteger('price')->default(0);
            $table->boolean('is_starter')->default(false);
            $table->timestamps();

            // Target of equipped_items (item_id, slot): an item can only be worn in its own slot
            $table->unique(['id', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
