<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->date('menu_date');
            $table->enum('meal_type', ['breakfast', 'lunch', 'snacks', 'dinner']);
            $table->timestamps();
            $table->unique(['menu_date', 'meal_type']);
        });

        Schema::create('menu_dishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['menu_id', 'dish_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_dishes');
        Schema::dropIfExists('menus');
    }
};
