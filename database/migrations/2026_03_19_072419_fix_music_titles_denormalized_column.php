<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Recompute the denormalized `titles` column for any rows where it drifted
     * out of sync (e.g. bulk-updated via query builder, bypassing model events).
     */
    public function up(): void
    {
        DB::statement("UPDATE musics SET titles = TRIM(title || COALESCE(' ' || subtitle, '')) WHERE titles IS DISTINCT FROM TRIM(title || COALESCE(' ' || subtitle, ''))");
    }

    public function down(): void
    {
        //
    }
};
