<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            // Drop existing non-unique indexes
            $table->dropIndex(['title']);
            $table->dropIndex(['abbreviation']);

            // Add unique indexes
            $table->unique(['title'], 'collections_title_unique');
            $table->unique(['abbreviation'], 'collections_abbreviation_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            // Drop unique indexes
            $table->dropUnique(['title']);
            $table->dropUnique(['abbreviation']);

            // Restore non-unique indexes
            $table->index(['title']);
            $table->index(['abbreviation']);
        });
    }
};
