<?php

namespace App\Http\Controllers;

use App\Models\DevicePairing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Where the borrowed screen collects the session its owner granted it.
 *
 * A plain page load rather than a step inside the poll, for two reasons.
 * Signing in rotates the session id — `SessionGuard::updateSession()` migrates
 * the session — so the id worth recording against the pairing only exists after
 * a real navigation, and this way the ordering is written down rather than
 * implied. The new cookie then arrives the way every other cookie does.
 *
 * Nothing here trusts the URL. The pairing is looked up by an id kept in this
 * browser's own session, so a token read off the screen by somebody else buys
 * them nothing.
 */
class QrLoginClaimController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $pairing = $this->claimable($request);

        if ($pairing === null) {
            return redirect()->route('qr-login');
        }

        $user = $pairing->user;

        // The gates that live in Fortify::authenticateUsing() rather than on the
        // model, re-applied: the phone passed them when it signed in, but that
        // may have been long enough ago for the answer to have changed.
        if ($user === null || $user->blocked || (config('app.only_admin_login') && ! $user->isAdmin())) {
            $pairing->revoke();

            return redirect()->route('qr-login');
        }

        // Deliberately without "remember": a remember cookie outlives the browser,
        // and this session is meant not to.
        Auth::login($user);

        $pairing->forceFill([
            'session_id' => $request->session()->getId(),
            'claimed_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
        ])->save();

        $request->session()->forget(DevicePairing::PENDING_SESSION_KEY);
        $request->session()->put(DevicePairing::DEVICE_SESSION_KEY, $pairing->id);

        return redirect()->route('plan-documents');
    }

    /**
     * The pairing this browser may spend, if there is one.
     *
     * Named by the session rather than by the URL, so the code on the screen is
     * not a credential anyone who photographs it can carry away.
     */
    private function claimable(Request $request): ?DevicePairing
    {
        $id = $request->session()->get(DevicePairing::PENDING_SESSION_KEY);

        if ($id === null) {
            return null;
        }

        $pairing = DevicePairing::query()
            ->whereKey($id)
            ->whereNotNull('approved_at')
            ->whereNull('claimed_at')
            ->whereNull('revoked_at')
            ->first();

        return $pairing !== null && ! $pairing->hasExpired() ? $pairing : null;
    }
}
