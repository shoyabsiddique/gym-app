<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('recommendation_date');
            $table->decimal('total_calories', 7, 2)->default(0);
            $table->decimal('total_protein', 6, 2)->default(0);
            $table->decimal('total_carbs', 6, 2)->default(0);
            $table->decimal('total_fat', 6, 2)->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'recommendation_date']);
        });

        Schema::create('recommendation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommendation_id')->constrained()->cascadeOnDelete();
            $table->enum('meal_type', ['breakfast', 'lunch', 'snacks', 'dinner']);
            $table->foreignId('dish_id')->constrained()->cascadeOnDelete();
            $table->decimal('servings', 4, 1)->default(1.0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_items');
        Schema::dropIfExists('recommendations');
    }
};
