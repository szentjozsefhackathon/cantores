<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The published surface of a score, frozen at the moment it was submitted.
 *
 * Without this, correcting a wrong accidental takes a score off the public shelf
 * until a reviewer gets to it: the site's answer to "there is an error in bar 12"
 * would be to remove the score rather than the error. With it, the public keeps
 * reading the last approved version while the correction waits in the queue, and
 * the reviewer reads a stable target rather than whatever exists at the instant
 * they press approve.
 *
 * Made at submission only — not on every save, not on a timer. Versioning is
 * publication-only: a borrower wants the newest reading, always.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('score_id')->constrained()->cascadeOnDelete();
            $table->text('content')->nullable();
            $table->string('format')->nullable();
            $table->json('settings')->nullable();

            // Score links as submitted: the blade prints them, so they are part of
            // what a reviewer judged.
            $table->json('urls')->nullable();

            $table->timestamps();

            $table->index(['score_id', 'created_at']);
        });

        Schema::create('score_version_file', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('score_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('score_file_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['score_version_id', 'score_file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_version_file');
        Schema::dropIfExists('score_versions');
    }
};
