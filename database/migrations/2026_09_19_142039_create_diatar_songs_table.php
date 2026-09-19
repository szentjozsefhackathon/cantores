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
        Schema::create('diatar_songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diatar_book_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('reference')->nullable();
            $table->unsignedInteger('source_order');
            $table->boolean('available')->default(true);
            $table->string('diagnostic_reason', 500)->nullable();
            $table->foreignId('last_seen_sync_run_id')->nullable()->constrained('diatar_sync_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['diatar_book_id', 'source_order']);
            $table->index(['diatar_book_id', 'reference']);
            $table->index(['diatar_book_id', 'available', 'source_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diatar_songs');
    }
};
