<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A counter of every open, signed in or not.
 *
 * The lender's list must not read as a complete guest book. Knowing the total
 * separately from the named openers is what lets it say „14 megnyitás · 5 ismert"
 * instead of a bare list of five people implying that is everyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->unsignedInteger('open_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->dropColumn('open_count');
        });
    }
};
