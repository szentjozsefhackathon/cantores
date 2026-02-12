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
        Schema::create('music_collection', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_id')->constrained('musics')->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->integer('page_number')->nullable();
            $table->string('order_number')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['music_id', 'collection_id']);
            $table->index(['collection_id', 'order_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_collection');
    }
};
