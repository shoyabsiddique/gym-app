<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('diet_type', ['veg', 'non_veg', 'eggetarian'])->default('non_veg')->after('target_fat');
            $table->json('allergies')->nullable()->after('diet_type');
        });

        Schema::table('dishes', function (Blueprint $table) {
            $table->enum('diet_type', ['veg', 'non_veg', 'eggetarian'])->default('veg')->after('ai_generated');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['diet_type', 'allergies']);
        });

        Schema::table('dishes', function (Blueprint $table) {
            $table->dropColumn('diet_type');
        });
    }
};
