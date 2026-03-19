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
            $table->foreignId('music_assignment_flag_id')
                ->nullable()
                ->after('notes')
                ->constrained()
                ->nullOnDelete();
        });

        // Migrate existing pivot data — keep only one flag per assignment (lowest flag id wins)
        DB::table('music_plan_slot_assignment_music_assignment_flag')
            ->orderBy('music_plan_slot_assignment_id')
            ->orderBy('music_assignment_flag_id')
            ->get()
            ->groupBy('music_plan_slot_assignment_id')
            ->each(function ($rows, $assignmentId) {
                DB::table('music_plan_slot_assignments')
                    ->where('id', $assignmentId)
                    ->update(['music_assignment_flag_id' => $rows->first()->music_assignment_flag_id]);
            });

        Schema::dropIfExists('music_plan_slot_assignment_music_assignment_flag');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('music_plan_slot_assignment_music_assignment_flag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_plan_slot_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('music_assignment_flag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['music_plan_slot_assignment_id', 'music_assignment_flag_id'], 'assignment_flag_unique');
        });

        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('music_assignment_flag_id');
        });
    }
};
