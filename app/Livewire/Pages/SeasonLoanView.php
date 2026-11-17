<?php

namespace App\Livewire\Pages;

use App\Enums\AttendanceStatus;
use App\Models\Loan;
use App\Models\MusicPlan;
use App\Models\Season;
use App\Models\SeasonAttendance;
use App\Models\SeasonEvent;
use App\Models\SeasonMember;
use App\Services\LoanAccessService;
use App\Services\LoanKeepingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A season as the choir sees it, on the link the leader sent round.
 *
 * The one lending link that writes. What it writes is narrow — a name on the
 * season's list, and that name's answer for a date — and it is written by
 * whoever holds the link, under whichever name they pick: a choir is a room of
 * people who know each other, and an account per alto is the friction that
 * would leave the list empty. The browser remembers the name it answered under,
 * in a cookie per season, so the second visit opens straight on the answers.
 *
 * Every action resolves the token again before it touches anything, so a
 * recalled link stops taking answers at the moment it stops showing the page.
 */
class SeasonLoanView extends Component
{
    /** Answers one browser may give in a minute: a whole season clicked through, not a script. */
    private const ANSWERS_PER_MINUTE = 120;

    /** New names one browser may add in an hour. */
    private const NAMES_PER_HOUR = 10;

    public string $loanToken = '';

    #[Locked]
    public int $seasonId;

    public string $seasonName = '';

    public string $ownerName = '';

    #[Locked]
    public ?int $memberId = null;

    public string $newName = '';

    public bool $showPast = false;

    public function mount(string $token): void
    {
        $loan = app(LoanAccessService::class)->resolveOfType($token, Season::class);

        abort_if(! $loan instanceof Loan, 404);

        $loan->touchLastViewed();

        app(LoanKeepingService::class)->recordOpen($loan, Auth::user());

        /** @var Season $season */
        $season = $loan->lendable;

        $this->loanToken = $token;
        $this->seasonId = $season->id;
        $this->seasonName = $season->name;
        $this->ownerName = $season->user?->displayName ?? '';
        $this->memberId = $this->rememberedMember($season)?->id;
    }

    /**
     * The season, through the link, on every request.
     */
    #[Computed]
    public function season(): Season
    {
        $loan = app(LoanAccessService::class)->resolveOfType($this->loanToken, Season::class);

        abort_if(! $loan instanceof Loan, 404);

        /** @var Season */
        return $loan->lendable;
    }

    /**
     * @return Collection<int, SeasonEvent>
     */
    #[Computed]
    public function events(): Collection
    {
        return $this->season->events()
            ->when(! $this->showPast, fn (Builder $query) => $query->whereDate('date', '>=', today()))
            ->with([
                'celebration',
                'attachments' => fn ($query) => $query->with('loan')->with(['lendable' => fn (MorphTo $morph) => $morph->morphWith([
                    MusicPlan::class => ['celebration'],
                ])]),
                'attendances.member',
            ])
            ->get();
    }

    #[Computed]
    public function pastCount(): int
    {
        return $this->season->events()->whereDate('date', '<', today())->count();
    }

    /**
     * @return Collection<int, SeasonMember>
     */
    #[Computed]
    public function members(): Collection
    {
        return $this->season->members()->get();
    }

    #[Computed]
    public function member(): ?SeasonMember
    {
        if ($this->memberId === null) {
            return null;
        }

        /** @var SeasonMember|null */
        return $this->season->members()->find($this->memberId);
    }

    /**
     * This member's answers, keyed by event id.
     *
     * @return array<int, AttendanceStatus>
     */
    #[Computed]
    public function myAnswers(): array
    {
        if (! $this->member instanceof SeasonMember) {
            return [];
        }

        return $this->member->attendances()
            ->pluck('status', 'season_event_id')
            ->map(fn ($status) => $status instanceof AttendanceStatus ? $status : AttendanceStatus::from($status))
            ->all();
    }

    /**
     * Answer as one of the names already on the list.
     */
    public function identify(int $memberId): void
    {
        /** @var SeasonMember $member */
        $member = $this->season->members()->findOrFail($memberId);

        $this->remember($member);
    }

    /**
     * Put a new name on the list and answer under it. A name already there,
     * written another way round in case, is that member rather than a second one.
     */
    public function join(): void
    {
        $season = $this->season;

        $validated = $this->validate([
            'newName' => ['required', 'string', 'max:60'],
        ]);

        $name = preg_replace('/\s+/u', ' ', trim($validated['newName']));

        $member = $season->members()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

        if (! $member instanceof SeasonMember) {
            $key = 'season-join:'.(Auth::id() ?? request()->ip());

            if (RateLimiter::tooManyAttempts($key, self::NAMES_PER_HOUR)) {
                $this->addError('newName', __('Too many new names from this browser. Please try again later.'));

                return;
            }

            RateLimiter::hit($key, 3600);

            $member = $season->members()->create(['name' => $name]);
        }

        $this->newName = '';

        $this->remember($member);
    }

    /**
     * Give, change or — by pressing the same answer again — take back this
     * member's answer for one date.
     */
    public function respond(int $eventId, string $status): void
    {
        $member = $this->member;

        if (! $member instanceof SeasonMember) {
            return;
        }

        $answer = AttendanceStatus::tryFrom($status);

        if ($answer === null) {
            return;
        }

        $key = 'season-answer:'.(Auth::id() ?? request()->ip());

        if (RateLimiter::tooManyAttempts($key, self::ANSWERS_PER_MINUTE)) {
            $this->dispatch('toast', message: __('Too many answers at once. Please wait a moment.'), type: 'danger');

            return;
        }

        RateLimiter::hit($key, 60);

        /** @var SeasonEvent $event */
        $event = $this->season->events()->findOrFail($eventId);

        $attendance = SeasonAttendance::query()
            ->where('season_event_id', $event->id)
            ->where('season_member_id', $member->id)
            ->first();

        if ($attendance?->status === $answer) {
            $attendance->delete();
        } else {
            SeasonAttendance::query()->updateOrCreate(
                ['season_event_id' => $event->id, 'season_member_id' => $member->id],
                ['status' => $answer],
            );
        }

        unset($this->events, $this->myAnswers);
    }

    /**
     * "Not me": stop answering under this name in this browser, and untie it
     * from the signed-in account if it was tied to it.
     */
    public function forget(): void
    {
        $member = $this->member;

        if ($member instanceof SeasonMember && Auth::check() && $member->user_id === Auth::id()) {
            $member->update(['user_id' => null]);
        }

        $this->memberId = null;

        Cookie::queue(Cookie::forget($this->cookieName()));

        unset($this->member, $this->myAnswers);
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::shell', [
            'title' => $this->seasonName,
            'noindex' => true,
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.season-loan-view');
    }

    /**
     * Who this browser answered as last time: the member tied to the signed-in
     * account, or the one named in this season's cookie.
     */
    private function rememberedMember(Season $season): ?SeasonMember
    {
        if (Auth::check()) {
            $own = $season->members()->where('user_id', Auth::id())->first();

            if ($own instanceof SeasonMember) {
                return $own;
            }
        }

        $remembered = request()->cookie($this->cookieName($season->id));

        if (! is_string($remembered) || ! ctype_digit($remembered)) {
            return null;
        }

        /** @var SeasonMember|null */
        return $season->members()->find((int) $remembered);
    }

    private function remember(SeasonMember $member): void
    {
        $this->memberId = $member->id;

        Cookie::queue(Cookie::forever($this->cookieName(), (string) $member->id, httpOnly: true));

        // A signed-in member is known on any device from now on — unless the
        // name is already someone else's, or this account already has one.
        if (Auth::check() && $member->user_id === null
            && ! $this->season->members()->where('user_id', Auth::id())->exists()) {
            $member->update(['user_id' => Auth::id()]);
        }

        unset($this->member, $this->members, $this->myAnswers);
    }

    private function cookieName(?int $seasonId = null): string
    {
        return 'season_member_'.($seasonId ?? $this->seasonId);
    }
}
