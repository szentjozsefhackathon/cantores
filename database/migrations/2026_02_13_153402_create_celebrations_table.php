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
        Schema::create('celebrations', function (Blueprint $table) {
            $table->id();
            $table->integer('celebration_key')->default(0);
            $table->date('actual_date');
            $table->string('name');
            $table->integer('season');
            $table->string('season_text')->nullable();
            $table->integer('week');
            $table->integer('day');
            $table->string('readings_code')->nullable();
            $table->char('year_letter', 1)->nullable();
            $table->string('year_parity')->nullable();
            $table->timestamps();

            // Unique constraint: actual_date + celebration_key
            $table->unique(['actual_date', 'celebration_key']);

            // Index for liturgical lookup (same as music_plans had)
            $table->index(['season', 'week', 'day'], 'celebrations_liturgical_lookup');
            $table->index('actual_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('celebrations');
    }
};
