<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The private access axis is a loan, not a share: the score stays its owner's,
 * access is temporary, and the reader is using someone else's work. The table
 * was four days old when the vocabulary was settled, so it is renamed here
 * rather than left to disagree with every name around it.
 *
 * The public link paths (/s/, /f/, /p/, /share/…) are untouched — those are
 * bearer tokens already in circulation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('shares', 'loans');

        Schema::table('loans', function (Blueprint $table): void {
            $table->renameColumn('shareable_id', 'lendable_id');
            $table->renameColumn('shareable_type', 'lendable_type');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->renameColumn('lendable_id', 'shareable_id');
            $table->renameColumn('lendable_type', 'shareable_type');
        });

        Schema::rename('loans', 'shares');
    }
};
