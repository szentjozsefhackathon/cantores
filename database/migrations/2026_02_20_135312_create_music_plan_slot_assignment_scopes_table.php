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
        Schema::create('music_plan_slot_assignment_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_plan_slot_assignment_id')->constrained('music_plan_slot_assignments')->cascadeOnDelete();
            $table->string('scope_type', 50);
            $table->integer('scope_number')->nullable();
            $table->timestamps();

            $table->index('music_plan_slot_assignment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_plan_slot_assignment_scopes');
    }
};
