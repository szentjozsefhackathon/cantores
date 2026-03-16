<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direktorium_editions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('original_filename');
            $table->string('file_path');
            $table->unsignedSmallInteger('total_pages')->nullable();
            $table->unsignedSmallInteger('processed_pages')->default(0);
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('processing_error')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direktorium_editions');
    }
};
