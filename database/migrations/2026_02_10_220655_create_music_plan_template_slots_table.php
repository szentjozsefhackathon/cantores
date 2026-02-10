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
        Schema::create('music_plan_template_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('music_plan_templates')->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained('music_plan_slots')->restrictOnDelete();
            $table->integer('sequence')->default(1);
            $table->boolean('is_included_by_default')->default(true);
            $table->timestamps();

            $table->unique(['template_id', 'slot_id']);
            $table->index(['template_id', 'sequence']);
            $table->index('is_included_by_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_plan_template_slots');
    }
};
