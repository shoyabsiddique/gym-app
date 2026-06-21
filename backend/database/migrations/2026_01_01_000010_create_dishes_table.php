<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dishes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('serving_size')->default('1 serving');
            $table->decimal('calories', 7, 2)->default(0);
            $table->decimal('protein', 6, 2)->default(0);
            $table->decimal('carbs', 6, 2)->default(0);
            $table->decimal('fat', 6, 2)->default(0);
            $table->decimal('fiber', 6, 2)->default(0);
            $table->decimal('sugar', 6, 2)->default(0);
            $table->decimal('sodium', 8, 2)->default(0);
            $table->boolean('ai_generated')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dishes');
    }
};
