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
        Schema::create('music_plan_slot_assignment_music_assignment_flag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_plan_slot_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('music_assignment_flag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['music_plan_slot_assignment_id', 'music_assignment_flag_id'], 'assignment_flag_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_plan_slot_assignment_music_assignment_flag');
    }
};
