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
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->integer('music_sequence')->default(1)->after('sequence');
            // Drop the existing three-column index to avoid duplicate index name conflict
            $table->dropIndex(['music_plan_id', 'music_plan_slot_id', 'sequence']);
            // Create a new four-column index that includes music_sequence
            $table->index(['music_plan_id', 'music_plan_slot_id', 'sequence', 'music_sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_plan_slot_assignments', function (Blueprint $table) {
            $table->dropIndex(['music_plan_id', 'music_plan_slot_id', 'sequence', 'music_sequence']);
            // Recreate the original three-column index
            $table->index(['music_plan_id', 'music_plan_slot_id', 'sequence']);
            $table->dropColumn('music_sequence');
        });
    }
};
