<?php

namespace App\Livewire\Pages;

use App\Models\DevicePairing;
use App\Services\SessionStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

/**
 * The phone, deciding whether the screen it has just read may come in.
 *
 * Reached by scanning, so the route is behind `auth` and a signed-out phone is
 * carried through Fortify's login and back by `url.intended` without this page
 * knowing about it.
 *
 * The tap is the point. Signing in on sight would mean any link anyone sent you
 * could quietly attach a browser you have never seen to your account; so the
 * page names the device and prints the confirmation code, and the person
 * checks it against the screen in front of them before agreeing. A code phished
 * from somewhere else has no second screen to match.
 */
class QrLoginApproval extends Component
{
    public string $token = '';

    public ?int $pairingId = null;

    /**
     * The pairing is unknown, spent, called off, or simply too old. One answer
     * for all four, so the page cannot be used to tell them apart.
     */
    public bool $invalid = false;

    public bool $approved = false;

    public bool $signedOut = false;

    public string $confirmationCode = '';

    public string $device = '';

    public ?string $ipAddress = null;

    public function mount(string $token): void
    {
        $this->token = $token;

        $pairing = DevicePairing::query()->where('token', $token)->first();

        if ($pairing === null || $pairing->hasExpired() || ! $pairing->isPending()) {
            $this->invalid = true;

            return;
        }

        $pairing->markScanned();

        $this->pairingId = $pairing->id;
        $this->confirmationCode = $pairing->confirmation_code;
        $this->device = $pairing->describeDevice();
        $this->ipAddress = $pairing->ip_address;
    }

    /**
     * Hand the device the account signed in on this phone.
     */
    public function approve(): void
    {
        $pairing = $this->pairing();

        if ($pairing === null || $pairing->hasExpired() || ! $pairing->isPending()) {
            $this->invalid = true;

            return;
        }

        $pairing->approveFor(Auth::user());

        $this->approved = true;
    }

    /**
     * Turn the device away, or sign it out again afterwards.
     */
    public function reject(SessionStore $sessions): void
    {
        $pairing = $this->pairing();

        if ($pairing === null) {
            $this->invalid = true;

            return;
        }

        if ($pairing->user_id !== null && $pairing->user_id !== Auth::id()) {
            $this->invalid = true;

            return;
        }

        $sessions->forget($pairing->session_id);
        $pairing->revoke();

        $this->approved = false;
        $this->signedOut = true;
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::auth', [
            'title' => __('Sign this device in?'),
            'noindex' => true,
            'logo' => true,
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.qr-login-approval');
    }

    private function pairing(): ?DevicePairing
    {
        return $this->pairingId === null
            ? null
            : DevicePairing::query()->find($this->pairingId);
    }
}
