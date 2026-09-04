<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a file stopped being part of its score, without its bytes going away.
 *
 * A published version points at the files it was approved against, so replacing
 * or removing a file can no longer destroy the bytes underneath an approved
 * snapshot. Rather than mutating the row and deleting the artifacts, a
 * replacement now supersedes the old row: it leaves the score's listing, and the
 * bytes stay for as long as a version refers to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('score_files', function (Blueprint $table): void {
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('score_files')->nullOnDelete();

            $table->index(['score_id', 'superseded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('score_files', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('superseded_by_id');
            $table->dropColumn('superseded_at');
        });
    }
};
