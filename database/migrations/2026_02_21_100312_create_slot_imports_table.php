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
        Schema::create('slot_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_plan_import_id')->constrained('music_plan_imports')->cascadeOnDelete();
            $table->string('name')->comment('Slot name (e.g., "Introitus", "végén")');
            $table->integer('column_number')->nullable()->comment('Column number in the table (0-indexed)');
            $table->foreignId('music_plan_slot_id')->nullable()->constrained('music_plan_slots')->nullOnDelete()->comment('Reference to existing MusicPlanSlot if found');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_imports');
    }
};
