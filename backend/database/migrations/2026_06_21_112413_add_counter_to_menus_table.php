<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('counter', 100)->default('General')->after('meal_type');
            $table->dropUnique(['menu_date', 'meal_type']);
            $table->unique(['menu_date', 'meal_type', 'counter']);
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropUnique(['menu_date', 'meal_type', 'counter']);
            $table->dropColumn('counter');
            $table->unique(['menu_date', 'meal_type']);
        });
    }
};
