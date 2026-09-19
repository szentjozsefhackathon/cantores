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
        Schema::create('diatar_books', function (Blueprint $table) {
            $table->id();
            $table->string('source_path')->unique();
            $table->string('title');
            $table->string('short_name')->nullable();
            $table->string('group')->nullable();
            $table->string('source_revision');
            $table->string('checksum', 64);
            $table->boolean('available')->default(true);
            $table->string('unavailable_reason', 500)->nullable();
            $table->foreignId('last_seen_sync_run_id')->nullable()->constrained('diatar_sync_runs')->nullOnDelete();
            $table->unsignedInteger('source_order')->default(0);
            $table->timestamps();

            $table->index(['available', 'source_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diatar_books');
    }
};
