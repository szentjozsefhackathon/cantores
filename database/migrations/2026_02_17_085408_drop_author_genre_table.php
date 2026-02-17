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
        Schema::dropIfExists('author_genre');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('author_genre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained('genres')->cascadeOnDelete();
            $table->timestamps();

            // Unique constraint to prevent duplicate relationships
            $table->unique(['author_id', 'genre_id']);
        });
    }
};
