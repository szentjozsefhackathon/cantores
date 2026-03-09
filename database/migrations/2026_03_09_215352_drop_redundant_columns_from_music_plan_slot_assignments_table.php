<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drop the redundant music_plan_id and music_plan_slot_id columns from
     * music_plan_slot_assignments. These are already accessible via the
     * music_plan_slot_plan_id foreign key, which references the
     * music_plan_slot_plan pivot table that holds both values.
     */
    public function up(): void
    {
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->dropForeign(['music_plan_id']);
            $table->dropForeign(['music_plan_slot_id']);
            $table->dropColumn(['music_plan_id', 'music_plan_slot_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->foreignId('music_plan_id')->constrained('music_plans')->cascadeOnDelete();
            $table->foreignId('music_plan_slot_id')->constrained('music_plan_slots')->cascadeOnDelete();
        });

        // Re-populate from the pivot table
        \Illuminate\Support\Facades\DB::statement('
            UPDATE music_plan_slot_assignments AS a
            SET music_plan_id = p.music_plan_id,
                music_plan_slot_id = p.music_plan_slot_id
            FROM music_plan_slot_plan AS p
            WHERE a.music_plan_slot_plan_id = p.id
        ');
    }
};
