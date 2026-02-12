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
        Schema::create('music_related', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_id')->constrained('musics')->cascadeOnDelete();
            $table->foreignId('related_music_id')->constrained('musics')->cascadeOnDelete();
            $table->string('relationship_type')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['music_id', 'related_music_id']);
            $table->index(['related_music_id', 'music_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_related');
    }
};
