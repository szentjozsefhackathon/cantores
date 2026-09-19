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
        Schema::create('diatar_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source_revision')->nullable()->index();
            $table->string('status');
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('indexed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('unavailable_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diatar_sync_runs');
    }
};
