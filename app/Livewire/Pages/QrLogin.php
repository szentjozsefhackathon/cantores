<?php

namespace App\Livewire\Pages;

use App\Models\DevicePairing;
use App\Services\QrCodeRenderer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

/**
 * The borrowed screen, waiting to be let in.
 *
 * Shows a code and nothing else, because that is the whole of what the church
 * laptop is for: a cantor walks up to a machine that is not theirs, points a
 * phone at it, and is signed in without typing an account in front of the
 * congregation.
 *
 * The pairing is minted on the first Livewire round-trip rather than in
 * `mount()`. A crawler has no cookie, so every visit would otherwise be a new
 * session and a new row, and the page has to stay crawlable for its `noindex`
 * to be read at all.
 */
class QrLogin extends Component
{
    /**
     * How long a screen nobody comes to is left polling.
     */
    public const ABANDON_MINUTES = 15;

    public ?int $pairingId = null;

    public ?string $token = null;

    public ?string $confirmationCode = null;

    /**
     * Set once a phone has the code open: the caption changes and the token
     * stops rotating.
     */
    public bool $scanned = false;

    /**
     * Set when nobody has come for a quarter of an hour. Polling stops and the
     * page offers to start again.
     */
    public bool $abandoned = false;

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirect(route('plan-documents'));
        }
    }

    /**
     * Mint or pick up this browser's pairing. Fired from `wire:init`.
     */
    public function startPairing(): void
    {
        $pairing = $this->pending();

        if ($pairing === null) {
            $pairing = DevicePairing::create([
                'token' => DevicePairing::generateToken(),
                'confirmation_code' => DevicePairing::generateConfirmationCode(),
                'requesting_session_id' => session()->getId(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'expires_at' => Carbon::now()->addMinutes(DevicePairing::TOKEN_MINUTES),
            ]);

            session()->put(DevicePairing::PENDING_SESSION_KEY, $pairing->id);
        } elseif ($pairing->hasExpired() && $pairing->scanned_at === null) {
            $pairing->rotate();
        }

        $this->adopt($pairing);
    }

    /**
     * The poll: has anyone come for this code yet?
     */
    public function checkPairing(): void
    {
        $pairing = $this->pairing();

        if ($pairing === null) {
            $this->startPairing();

            return;
        }

        if ($pairing->isApproved()) {
            $this->redirect(route('qr-login.claim'));

            return;
        }

        if ($pairing->revoked_at !== null) {
            $this->restart();

            return;
        }

        if ($pairing->created_at?->addMinutes(self::ABANDON_MINUTES)->isPast()) {
            $this->abandoned = true;

            return;
        }

        if ($pairing->hasExpired()) {
            // A code nobody has picked up is free to replace; one that a phone is
            // looking at is not, so a scanned pairing simply runs out instead.
            if ($pairing->scanned_at !== null) {
                $this->restart();

                return;
            }

            $pairing->rotate();
        }

        $this->adopt($pairing);
    }

    /**
     * Throw the current pairing away and start a fresh one.
     */
    public function restart(): void
    {
        $this->pairing()?->revoke();

        $this->reset('pairingId', 'token', 'confirmationCode', 'scanned', 'abandoned');

        $this->startPairing();
    }

    /**
     * The QR itself, rebuilt whenever the token changes.
     */
    public function qrCodeSvg(QrCodeRenderer $renderer): ?string
    {
        if ($this->token === null) {
            return null;
        }

        return $renderer->toSvg(route('qr-login.approve', ['token' => $this->token]));
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::auth', [
            'title' => __('Sign in with a QR code'),
            'noindex' => true,
            'logo' => true,
        ]);
    }

    public function render(QrCodeRenderer $renderer): IlluminateView
    {
        return view('livewire.pages.qr-login', [
            'qrCodeSvg' => $this->qrCodeSvg($renderer),
        ]);
    }

    private function pairing(): ?DevicePairing
    {
        return $this->pairingId === null
            ? null
            : DevicePairing::query()->find($this->pairingId);
    }

    /**
     * The invitation this browser is already waiting on, if it still stands.
     */
    private function pending(): ?DevicePairing
    {
        $id = session()->get(DevicePairing::PENDING_SESSION_KEY);

        if ($id === null) {
            return null;
        }

        $pairing = DevicePairing::query()
            ->whereKey($id)
            ->whereNull('claimed_at')
            ->whereNull('revoked_at')
            ->first();

        return $pairing;
    }

    private function adopt(DevicePairing $pairing): void
    {
        $this->pairingId = $pairing->id;
        $this->token = $pairing->token;
        $this->confirmationCode = $pairing->confirmation_code;
        $this->scanned = $pairing->scanned_at !== null;
    }
}
