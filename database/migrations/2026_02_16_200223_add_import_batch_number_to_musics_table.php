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
        Schema::table('musics', function (Blueprint $table) {
            $table->unsignedInteger('import_batch_number')->nullable()->after('is_private');
            $table->index('import_batch_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('musics', function (Blueprint $table) {
            $table->dropIndex(['import_batch_number']);
            $table->dropColumn('import_batch_number');
        });
    }
};
