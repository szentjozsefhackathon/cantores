<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('celebrations', function (Blueprint $table) {
            // Add user_id foreign key (nullable, as liturgical celebrations have no user)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Add is_custom boolean flag
            $table->boolean('is_custom')->default(false);

            // Make liturgical fields nullable (custom celebrations may not have them)
            $table->integer('season')->nullable()->change();
            $table->integer('week')->nullable()->change();
            $table->integer('day')->nullable()->change();
        });

        // Drop the existing unique constraint (actual_date, celebration_key)
        Schema::table('celebrations', function (Blueprint $table) {
            $table->dropUnique(['actual_date', 'celebration_key']);
        });

        // Create a conditional unique index for liturgical celebrations (is_custom = false)
        // This ensures that for non-custom celebrations, the combination of actual_date and celebration_key is unique.
        DB::statement('
            CREATE UNIQUE INDEX celebrations_liturgical_unique
            ON celebrations (actual_date, celebration_key)
            WHERE is_custom = false
        ');

        // Create a conditional unique index for custom celebrations (is_custom = true)
        // This ensures that for custom celebrations, the combination of actual_date, celebration_key, and user_id is unique.
        DB::statement('
            CREATE UNIQUE INDEX celebrations_custom_unique
            ON celebrations (actual_date, celebration_key, user_id)
            WHERE is_custom = true
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the conditional indexes first
        DB::statement('DROP INDEX IF EXISTS celebrations_liturgical_unique');
        DB::statement('DROP INDEX IF EXISTS celebrations_custom_unique');

        Schema::table('celebrations', function (Blueprint $table) {
            // Restore the original unique constraint
            $table->unique(['actual_date', 'celebration_key']);

            // Revert liturgical fields to not nullable (set default 0)
            $table->integer('season')->nullable(false)->change();
            $table->integer('week')->nullable(false)->change();
            $table->integer('day')->nullable(false)->change();

            // Remove added columns
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropColumn('is_custom');
        });
    }
};
