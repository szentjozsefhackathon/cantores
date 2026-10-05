<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which style a deck is shown in. None means the factory defaults, which is
 * every deck until now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projections', function (Blueprint $table) {
            $table->foreignId('projection_style_id')->nullable()->after('ratio')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('projection_style_id');
        });
    }
};
