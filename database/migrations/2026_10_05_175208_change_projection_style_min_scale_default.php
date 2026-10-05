<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A new style may shrink a page to 90% rather than 85% to save a slide. Styles
 * that already exist keep what they were given.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projection_styles', function (Blueprint $table) {
            $table->float('min_scale')->default(0.9)->change();
        });
    }

    public function down(): void
    {
        Schema::table('projection_styles', function (Blueprint $table) {
            $table->float('min_scale')->default(0.85)->change();
        });
    }
};
