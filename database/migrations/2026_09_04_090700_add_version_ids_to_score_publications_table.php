<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the public is reading, and what the reviewer is looking at.
 *
 * These are different rows whenever a correction is waiting in the queue, which
 * is the whole point of versioning the published surface.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('score_publications', function (Blueprint $table): void {
            $table->foreignId('approved_version_id')->nullable()->constrained('score_versions')->nullOnDelete();
            $table->foreignId('submitted_version_id')->nullable()->constrained('score_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('score_publications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_version_id');
            $table->dropConstrainedForeignId('submitted_version_id');
        });
    }
};
