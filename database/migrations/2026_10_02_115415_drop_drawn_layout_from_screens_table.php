<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The wall no longer reports where it cut the deck, and the phone no longer
     * copies it.
     *
     * Every browser cuts a deck at the deck's own ratio by the same rules, so
     * the same deck comes to the same slides everywhere; a device that cuts it
     * differently is measuring something differently, and that is put right
     * where it is measured rather than papered over by copying another
     * device's answer.
     */
    public function up(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn('drawn_layout');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->json('drawn_layout')->nullable()->after('drawn_revision');
        });
    }
};
