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
        Schema::create('music_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_plan_import_item_id')->constrained('music_plan_import_items')->cascadeOnDelete();
            $table->foreignId('slot_import_id')->constrained('slot_imports')->restrictOnDelete();
            $table->foreignId('music_id')->nullable()->constrained('musics')->nullOnDelete()->comment('Reference to existing Music if found');
            $table->string('abbreviation')->comment('Music abbreviation (e.g., "ÉE281", "Ho132")');
            $table->string('label')->nullable()->comment('Label for this music import (e.g., "alternative")');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_imports');
    }
};
