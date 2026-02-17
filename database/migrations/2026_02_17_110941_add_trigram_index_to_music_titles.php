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
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS musics_titles_trgm ON musics USING GIN (titles gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS musics_custom_id_trgm ON musics USING GIN (custom_id gin_trgm_ops)');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_titles', function (Blueprint $table) {
            DB::statement('DROP INDEX IF EXISTS musics_titles_trgm');
            DB::statement('DROP INDEX IF EXISTS musics_custom_id_trgm');
        });
    }
};
