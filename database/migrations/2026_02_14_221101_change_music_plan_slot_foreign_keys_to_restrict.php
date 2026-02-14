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
        // Drop the existing foreign key constraints
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->dropForeign(['music_plan_slot_id']);
        });

        Schema::table('music_plan_slot_plan', function (Blueprint $table) {
            $table->dropForeign(['music_plan_slot_id']);
        });

        // Re-add them with restrictOnDelete
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->foreign('music_plan_slot_id')
                ->references('id')
                ->on('music_plan_slots')
                ->restrictOnDelete();
        });

        Schema::table('music_plan_slot_plan', function (Blueprint $table) {
            $table->foreign('music_plan_slot_id')
                ->references('id')
                ->on('music_plan_slots')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the restrict foreign keys
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->dropForeign(['music_plan_slot_id']);
        });

        Schema::table('music_plan_slot_plan', function (Blueprint $table) {
            $table->dropForeign(['music_plan_slot_id']);
        });

        // Re-add them with cascadeOnDelete (original behavior)
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->foreign('music_plan_slot_id')
                ->references('id')
                ->on('music_plan_slots')
                ->cascadeOnDelete();
        });

        Schema::table('music_plan_slot_plan', function (Blueprint $table) {
            $table->foreign('music_plan_slot_id')
                ->references('id')
                ->on('music_plan_slots')
                ->cascadeOnDelete();
        });
    }
};
