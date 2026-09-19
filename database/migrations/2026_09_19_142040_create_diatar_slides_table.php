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
        Schema::create('diatar_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diatar_song_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('source_order');
            $table->string('external_id', 8)->nullable();
            $table->string('verse_name')->default('');
            $table->boolean('is_exportable')->default(false);
            $table->string('diagnostic_reason', 500)->nullable();
            $table->foreignId('last_seen_sync_run_id')->nullable()->constrained('diatar_sync_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['diatar_song_id', 'source_order']);
            $table->index('external_id');
            $table->index(['diatar_song_id', 'is_exportable', 'source_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diatar_slides');
    }
};
