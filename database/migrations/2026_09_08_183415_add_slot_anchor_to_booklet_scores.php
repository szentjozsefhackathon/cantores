<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where in the service this row stands.
     *
     * A score already says which assignment it was chosen from, and the slot
     * could be reached through it — but the booklet is now read as the plan
     * itself, and two kinds of row cannot answer that way: a paragraph of
     * instructions, which was chosen from no music at all, and a score whose
     * assignment has since been deleted from the plan. Both still belong
     * somewhere in the service, so the slot is named here rather than inferred.
     *
     * Null means the row stands outside the plan: the booklet's opening words,
     * or a score that has lost its place in it.
     */
    public function up(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->foreignId('music_plan_slot_plan_id')
                ->nullable()
                ->after('music_plan_slot_assignment_id')
                ->constrained('music_plan_slot_plan')
                ->nullOnDelete();
        });

        // Every existing score row was chosen from an assignment, and the
        // assignment knows its slot: the column starts out saying what could
        // until now only be joined for.
        DB::table('booklet_scores')
            ->whereNotNull('music_plan_slot_assignment_id')
            ->update([
                'music_plan_slot_plan_id' => DB::raw(
                    '(select music_plan_slot_plan_id from music_plan_slot_assignments
                        where music_plan_slot_assignments.id = booklet_scores.music_plan_slot_assignment_id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('music_plan_slot_plan_id');
        });
    }
};
