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
        Schema::create('diatar_music_binding_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diatar_music_binding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diatar_slide_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->timestamps();

            $table->unique(['diatar_music_binding_id', 'sequence']);
            $table->index(['diatar_music_binding_id', 'diatar_slide_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diatar_music_binding_slides');
    }
};
