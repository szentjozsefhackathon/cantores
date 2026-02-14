<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->foreignId('music_plan_slot_plan_id')
                ->nullable()
                ->constrained('music_plan_slot_plan')
                ->cascadeOnDelete();
        });

        // Delete orphaned assignments that have no matching pivot row
        DB::statement('
            DELETE FROM music_plan_slot_assignments AS a
            WHERE NOT EXISTS (
                SELECT 1 FROM music_plan_slot_plan AS p
                WHERE a.music_plan_id = p.music_plan_id
                    AND a.music_plan_slot_id = p.music_plan_slot_id
                    AND a.sequence = p.sequence
            )
        ');

        // Populate the new column by matching pivot rows
        DB::statement('
            UPDATE music_plan_slot_assignments AS a
            SET music_plan_slot_plan_id = p.id
            FROM music_plan_slot_plan AS p
            WHERE a.music_plan_id = p.music_plan_id
                AND a.music_plan_slot_id = p.music_plan_slot_id
                AND a.sequence = p.sequence
        ');

        // After population, make the column non-nullable
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->foreignId('music_plan_slot_plan_id')->nullable(false)->change();
        });

        // Drop the sequence column (no longer needed)
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->dropColumn('sequence');
        });

        // Update indexes: remove old composite index that included sequence
        DB::statement('DROP INDEX IF EXISTS music_plan_slot_assignments_music_plan_id_music_plan_slot_id_se');
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            // Add new index with pivot id
            $table->index(['music_plan_slot_plan_id', 'music_sequence'], 'music_plan_slot_assignments_pivot_music_sequence_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore sequence column
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->integer('sequence')->default(1);
        });

        // Populate sequence from pivot
        DB::statement('
            UPDATE music_plan_slot_assignments AS a
            SET sequence = p.sequence
            FROM music_plan_slot_plan AS p
            WHERE a.music_plan_slot_plan_id = p.id
        ');

        // Drop new index
        DB::statement('DROP INDEX IF EXISTS music_plan_slot_assignments_pivot_music_sequence_index');

        // Restore old index
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->index(['music_plan_id', 'music_plan_slot_id', 'sequence', 'music_sequence'], 'music_plan_slot_assignments_music_plan_id_music_plan_slot_id_se');
        });

        // Drop foreign key and column
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->dropForeign(['music_plan_slot_plan_id']);
            $table->dropColumn('music_plan_slot_plan_id');
        });
    }
};
