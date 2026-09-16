<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which of the document's own musics a row was chosen from.
     *
     * One name on both tables, as music_plan_slot_assignment_id already is, so
     * that App\Contracts\PlanEntry can state it once. Removing the music removes
     * its rows.
     */
    public function up(): void
    {
        Schema::table('projection_slides', function (Blueprint $table) {
            $table->foreignId('added_music_id')->nullable()->after('music_plan_slot_plan_id')->constrained('projection_musics')->cascadeOnDelete();
        });

        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->foreignId('added_music_id')->nullable()->after('music_plan_slot_plan_id')->constrained('booklet_musics')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projection_slides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('added_music_id');
        });

        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('added_music_id');
        });
    }
};
