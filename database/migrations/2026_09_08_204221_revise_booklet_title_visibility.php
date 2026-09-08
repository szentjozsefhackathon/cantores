<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every heading is now the row's own call, not the booklet's.
     *
     * There used to be one switch over the whole booklet that hid every heading
     * at once, and a per-row switch each for the music's name and the variation.
     * The slot's name had no switch at all — it was only ever silenced by the
     * booklet-wide one. So the booklet-wide switch goes, the slot's name gets a
     * switch of its own, and all three now read the same way: the slot and the
     * music are printed unless a row says not to, the variation is printed only
     * where a row asks for it.
     */
    public function up(): void
    {
        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->boolean('show_slot')->default(true)->after('start_on_new_page');
        });

        Schema::table('booklets', function (Blueprint $table) {
            $table->dropColumn('show_titles');
        });
    }

    public function down(): void
    {
        Schema::table('booklets', function (Blueprint $table) {
            $table->boolean('show_titles')->default(true)->after('staff_height_mm');
        });

        Schema::table('booklet_scores', function (Blueprint $table) {
            $table->dropColumn('show_slot');
        });
    }
};
