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
        Schema::create('music_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('celebration_name');
            $table->date('actual_date');
            $table->string('setting')->default('organist');
            $table->integer('season');
            $table->integer('week');
            $table->integer('day');
            $table->string('readings_code')->nullable();
            $table->char('year_letter', 1)->nullable();
            $table->string('year_parity')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['season', 'week', 'day'], 'music_plans_liturgical_lookup');
            $table->index('actual_date');
            $table->index(['user_id', 'is_published']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_plans');
    }
};
