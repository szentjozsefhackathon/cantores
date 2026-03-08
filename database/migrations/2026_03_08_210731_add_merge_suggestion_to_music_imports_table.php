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
        Schema::table('music_imports', function (Blueprint $table) {
            $table->string('merge_suggestion')->nullable()->after('label')
                ->comment('Original slash-separated abbreviation when merge candidates were detected (e.g. "ÉE267/H23")');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_imports', function (Blueprint $table) {
            $table->dropColumn('merge_suggestion');
        });
    }
};
