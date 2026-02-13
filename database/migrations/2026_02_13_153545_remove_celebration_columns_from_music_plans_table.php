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
        Schema::table('music_plans', function (Blueprint $table) {
            // Remove celebration-related columns
            // Indexes that depend on these columns will be dropped automatically
            $table->dropColumn([
                'celebration_name',
                'actual_date',
                'season',
                'season_text',
                'week',
                'day',
                'readings_code',
                'year_letter',
                'year_parity',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_plans', function (Blueprint $table) {
            // Re-add the columns
            $table->string('celebration_name')->nullable();
            $table->date('actual_date');
            $table->integer('season');
            $table->string('season_text')->nullable();
            $table->integer('week');
            $table->integer('day');
            $table->string('readings_code')->nullable();
            $table->char('year_letter', 1)->nullable();
            $table->string('year_parity')->nullable();

            // Re-add indexes
            $table->index(['season', 'week', 'day'], 'music_plans_liturgical_lookup');
            $table->index('actual_date');
        });
    }
};
