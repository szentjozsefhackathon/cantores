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
        Schema::create('score_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64)->index();
            $table->string('rights', 32);
            $table->string('render_status', 32);
            $table->text('render_error')->nullable();
            $table->boolean('has_thumbnail')->default(false);
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->timestamp('rendered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('score_files');
    }
};
