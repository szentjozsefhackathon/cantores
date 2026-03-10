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
        // Add celebration_id nullable FK to music_plans
        Schema::table('music_plans', function (Blueprint $table) {
            $table->foreignId('celebration_id')->nullable()->constrained()->nullOnDelete()->after('user_id');
        });

        // Migrate existing pivot data: copy first celebration per plan into the new column
        DB::statement('
            UPDATE music_plans
            SET celebration_id = (
                SELECT celebration_id
                FROM celebration_music_plan
                WHERE celebration_music_plan.music_plan_id = music_plans.id
                LIMIT 1
            )
        ');

        // Drop the pivot table
        Schema::dropIfExists('celebration_music_plan');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the pivot table
        Schema::create('celebration_music_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('celebration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('music_plan_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['celebration_id', 'music_plan_id']);
            $table->index('celebration_id');
            $table->index('music_plan_id');
        });

        // Migrate data back to pivot
        DB::statement('
            INSERT INTO celebration_music_plan (celebration_id, music_plan_id, created_at, updated_at)
            SELECT celebration_id, id, NOW(), NOW()
            FROM music_plans
            WHERE celebration_id IS NOT NULL
        ');

        // Drop the direct FK column
        Schema::table('music_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('celebration_id');
        });
    }
};
