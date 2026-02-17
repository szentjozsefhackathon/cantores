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
        Schema::create('music_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_id')
                ->constrained('musics')
                ->onDelete('cascade');
            $table->foreignId('verifier_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->string('field_name');
            $table->unsignedBigInteger('pivot_reference')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('music_id');
            $table->index('verifier_id');
            $table->index('status');
            $table->index(['music_id', 'field_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_verifications');
    }
};
