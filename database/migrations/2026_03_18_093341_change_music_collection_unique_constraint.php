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
        Schema::table('music_collection', function (Blueprint $table) {
            $table->dropUnique('music_collection_music_id_collection_id_unique');
            $table->unique(['music_id', 'collection_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::table('music_collection', function (Blueprint $table) {
            $table->dropUnique(['music_id', 'collection_id', 'order_number']);
            $table->unique(['music_id', 'collection_id']);
        });
    }
};
