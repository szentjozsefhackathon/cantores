<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the lender last looked at what a folder or plan loan opens.
 *
 * A container grows on its own — a score added to the folder, a music assigned to
 * the plan — and everything is lent by default, so the lender is told what has
 * joined since rather than left to notice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->timestamp('contents_reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->dropColumn('contents_reviewed_at');
        });
    }
};
