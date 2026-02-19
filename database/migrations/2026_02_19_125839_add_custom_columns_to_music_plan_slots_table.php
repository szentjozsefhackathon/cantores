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
        Schema::table('music_plan_slots', function (Blueprint $table) {
            // Add new columns
            $table->foreignId('music_plan_id')
                ->nullable()
                ->constrained('music_plans')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade');

            $table->boolean('is_custom')
                ->default(false)
                ->index();

            // Add composite index for custom slots
            $table->index(['is_custom', 'music_plan_id'], 'idx_music_plan_slots_custom');

            // Add partial index for global slots (where is_custom = false)
            // Note: Laravel doesn't support partial indexes directly, we'll use raw SQL
        });

        // Add partial index using raw SQL for better performance
        DB::statement('CREATE INDEX idx_music_plan_slots_global ON music_plan_slots (id) WHERE is_custom = false');

        // Update existing rows to have is_custom = false
        DB::table('music_plan_slots')->update(['is_custom' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_plan_slots', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_music_plan_slots_custom');

            // Drop foreign keys
            $table->dropForeign(['music_plan_id']);
            $table->dropForeign(['user_id']);

            // Drop columns
            $table->dropColumn(['music_plan_id', 'user_id', 'is_custom']);
        });

        // Drop partial index
        DB::statement('DROP INDEX IF EXISTS idx_music_plan_slots_global');
    }
};
