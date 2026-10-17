<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The shape of the window the wall draws into, as its width over its height.
     *
     * The phone moves the picture with the fit, and the fit is relative to the
     * wall: without the wall's own shape the phone can only move the picture
     * inside a band of its own, which crops where the wall does not and shows
     * what the wall has already lost. Null until the wall has said, which the
     * phone reads as the deck's own shape — the preview it always drew.
     */
    public function up(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->float('shape')->nullable()->after('fit_y');
        });
    }

    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn('shape');
        });
    }
};
