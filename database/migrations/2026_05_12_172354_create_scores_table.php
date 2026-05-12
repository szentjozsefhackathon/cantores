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
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('music_id')->nullable()->constrained('musics')->nullOnDelete();
            $table->string('title');
            $table->string('format', 16);
            $table->longText('content');
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
            $table->index(['music_id', 'user_id']);
            $table->index('format');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};
