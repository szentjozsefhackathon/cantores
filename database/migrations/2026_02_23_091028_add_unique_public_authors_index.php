<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create a partial unique index for public authors (is_private = false)
        // This ensures no two public authors have the same name
        DB::statement('
            CREATE UNIQUE INDEX authors_name_public_unique 
            ON authors (name) 
            WHERE is_private = false
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS authors_name_public_unique');
    }
};
