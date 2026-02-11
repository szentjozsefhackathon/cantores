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
        Schema::create('music_plan_slot_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('music_plan_slot_id')->constrained()->cascadeOnDelete();
            $table->integer('sequence')->default(1);
            $table->unique(['music_plan_id', 'music_plan_slot_id'], 'music_plan_slot_plan_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_plan_slot_plan');
    }
};
