<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A screen is a device, not a session.
     *
     * The session was the right key while a screen was anonymous: it is what
     * makes a browser that browser, and keying on it is what makes a second
     * window of the same browser deliberately the same screen. It stays true —
     * the device cookie keeps that property exactly, because a second window
     * carries the same cookie — but it is not durable. A session is two hours,
     * so the parish laptop grew a new row every Sunday and orphaned the last
     * one, and a name hung on the row would have gone with it.
     *
     * The session is still recorded, because it is still what the heartbeat and
     * the policies are reasoning about, but it stops being unique: a browser
     * whose device cookie was cleared while its session survived is a new device
     * with an old session, and that is a new row rather than a collision.
     */
    public function up(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            // Minted in the browser's own cookie and good for years. Nullable
            // only for the moment between adding the column and filling it.
            $table->uuid('device_id')->nullable()->after('user_id');
        });

        // Every screen standing today was claimed by a session, and that session
        // is the best thing known about the browser it belongs to. Seeding the
        // device from it keeps those rows addressable rather than stranding them
        // beside the new ones their browsers will claim.
        DB::table('screens')->whereNull('device_id')->update([
            'device_id' => DB::raw(Schema::getConnection()->getDriverName() === 'pgsql'
                ? 'md5(session_id)::uuid'
                : 'session_id'),
        ]);

        Schema::table('screens', function (Blueprint $table) {
            $table->uuid('device_id')->nullable(false)->change();
            $table->unique('device_id');
            $table->dropUnique(['session_id']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
            $table->unique('session_id');
            $table->dropUnique(['device_id']);
            $table->dropColumn('device_id');
        });
    }
};
