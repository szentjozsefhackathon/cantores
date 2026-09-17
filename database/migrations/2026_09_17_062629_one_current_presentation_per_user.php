<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One show per person.
     *
     * A screen used to point at a presentation of its own, so one person could
     * have two devices showing two decks, each with its own position. Now a
     * screen always shows its owner's current presentation, and "current" is
     * the one row per person that has not been ended — which the partial index
     * makes the database's promise rather than the application's hope.
     *
     * Every un-ended row but the newest is ended on the way, since the index
     * cannot be built over the multi-show rows it forbids.
     *
     * `presentations.device_pairing_id` goes too: it was recorded and never
     * read.
     */
    public function up(): void
    {
        DB::statement('
            UPDATE presentations
            SET ended_at = now()
            WHERE ended_at IS NULL
              AND id NOT IN (
                  SELECT DISTINCT ON (user_id) id
                  FROM presentations
                  WHERE ended_at IS NULL
                  ORDER BY user_id, last_seen_at DESC, id DESC
              )
        ');

        DB::statement('
            CREATE UNIQUE INDEX presentations_one_current_per_user
            ON presentations (user_id)
            WHERE ended_at IS NULL
        ');

        Schema::table('screens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('presentation_id');
        });

        Schema::table('presentations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('device_pairing_id');
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->foreignId('device_pairing_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('screens', function (Blueprint $table) {
            $table->foreignId('presentation_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::statement('DROP INDEX IF EXISTS presentations_one_current_per_user');
    }
};
