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
        Schema::create('diatar_music_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_id')->constrained('musics')->cascadeOnDelete();
            $table->foreignId('diatar_song_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('editor_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['music_id', 'diatar_song_id']);
            $table->index(['music_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diatar_music_bindings');
    }
};
