<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * The one place that reaches into the framework's own `sessions` table.
 *
 * That table is Laravel's, not ours: it has no model, and the application is
 * otherwise held to Eloquent. Signing a borrowed laptop out from a phone means
 * ending a session that belongs to another browser, and there is no other way
 * to say that — so the raw access is gathered here, behind a name, and reads
 * the table and connection out of the session config rather than assuming them.
 *
 * It is deliberately best-effort. Only the `database` driver keeps sessions
 * anywhere this can reach, so revocation is *enforced* by
 * \App\Http\Middleware\EnforcePairedDeviceSession, and this merely makes it
 * immediate.
 */
class SessionStore
{
    /**
     * Whether sessions are kept somewhere this service can reach them.
     */
    public function isQueryable(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * End a session by id, wherever it is stored. Returns whether a row went.
     */
    public function forget(?string $sessionId): bool
    {
        if ($sessionId === null || ! $this->isQueryable()) {
            return false;
        }

        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->delete() > 0;
    }
}
