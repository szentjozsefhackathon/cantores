<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A 6 mm staff, which is what a printed booklet wants under 10.5pt lyrics.
     *
     * Only the default changes: a booklet already laid out keeps its staff.
     */
    public function up(): void
    {
        Schema::table('booklets', function (Blueprint $table) {
            $table->float('staff_height_mm')->default(6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('booklets', function (Blueprint $table) {
            $table->float('staff_height_mm')->default(5)->change();
        });
    }
};
