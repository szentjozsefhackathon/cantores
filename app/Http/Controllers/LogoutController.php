<?php

namespace App\Http\Controllers;

use App\Models\DevicePairing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LogoutResponse;

/**
 * Signing out, with one exception for screens signed in by QR code.
 *
 * Takes the place of Fortify's own logout, which calls `SessionGuard::logout()`
 * — and that rotates the user's remember token. There is one token per user,
 * shared by every browser that ticked "remember me", so an ordinary logout
 * signs the person out of every remembered browser, and that is kept: it is
 * what makes a stolen remember cookie worthless once its owner signs out.
 *
 * A paired screen is different. It was never given a remember cookie, so
 * rotating the token protects nothing there — it only signed the phone's owner
 * out of their computer at home the next time its session ran out. Such a
 * session is ended with `logoutCurrentDevice()`, which leaves the token alone.
 *
 * Registered over Fortify's route of the same name: application routes load
 * after the packages have booted, so this one is the one that answers.
 *
 * @see QrLoginClaimController
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request): LogoutResponse
    {
        $guard = Auth::guard(config('fortify.guard'));

        if ($request->session()->has(DevicePairing::DEVICE_SESSION_KEY)) {
            $guard->logoutCurrentDevice();
        } else {
            $guard->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return app(LogoutResponse::class);
    }
}
