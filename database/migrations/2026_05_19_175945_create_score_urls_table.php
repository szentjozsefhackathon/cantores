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
        Schema::create('score_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('label')->nullable();
            $table->string('comment')->nullable();
            $table->timestamps();

            $table->index('score_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('score_urls');
    }
};
