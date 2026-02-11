<?php

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
        Schema::table('music_plan_slot_plan', function (Blueprint $table) {
            $table->dropUnique('music_plan_slot_plan_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_plan_slot_plan', function (Blueprint $table) {
            $table->unique(['music_plan_id', 'music_plan_slot_id'], 'music_plan_slot_plan_unique');
        });
    }
};
