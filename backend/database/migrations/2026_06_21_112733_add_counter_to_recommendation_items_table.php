<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendation_items', function (Blueprint $table) {
            $table->string('counter', 100)->default('General')->after('dish_id');
        });
    }

    public function down(): void
    {
        Schema::table('recommendation_items', function (Blueprint $table) {
            $table->dropColumn('counter');
        });
    }
};
