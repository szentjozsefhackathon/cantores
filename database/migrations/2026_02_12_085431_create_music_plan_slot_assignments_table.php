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
        Schema::create('music_plan_slot_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_plan_id')->constrained('music_plans')->cascadeOnDelete();
            $table->foreignId('music_plan_slot_id')->constrained('music_plan_slots')->cascadeOnDelete();
            $table->foreignId('music_id')->constrained('musics')->cascadeOnDelete();
            $table->integer('sequence')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes (no unique constraint to allow repeats)
            $table->index(['music_plan_id', 'music_plan_slot_id', 'music_id']);
            $table->index(['music_plan_id', 'music_plan_slot_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_plan_slot_assignments');
    }
};
