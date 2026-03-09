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
            $table->json('flags')->nullable()->after('merge_suggestion')->comment('Flags to apply when creating assignments, e.g. ["low_priority"]');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_imports', function (Blueprint $table) {
            $table->dropColumn('flags');
        });
    }
};
