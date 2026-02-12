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
        Schema::create('music_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_id')->constrained('musics')->cascadeOnDelete();
            $table->string('url')->nullable(false);
            $table->string('label')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('music_id');
            $table->index('url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_urls');
    }
};
