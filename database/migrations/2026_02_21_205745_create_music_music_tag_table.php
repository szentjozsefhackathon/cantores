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
        Schema::create('music_music_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_id')->constrained('musics')->onDelete('cascade');
            $table->foreignId('music_tag_id')->constrained('music_tags')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['music_id', 'music_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_music_tag');
    }
};
