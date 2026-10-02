<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the wall cut each row of the deck it drew into slides.
     *
     * Every browser engraves the deck for itself, and two browsers measure the
     * same words a pixel apart: a page that is one slide on the projector could
     * be two on the phone, and every address after it would name a different
     * slide on each. The room sees the wall, so the wall's cuts are the ones
     * the phone is told to make. Beside `drawn_revision`, because a cut is only
     * true of the deck it was made in.
     */
    public function up(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->json('drawn_layout')->nullable()->after('drawn_revision');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn('drawn_layout');
        });
    }
};
