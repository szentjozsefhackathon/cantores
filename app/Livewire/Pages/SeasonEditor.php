<?php

namespace App\Livewire\Pages;

use App\Enums\AttendanceStatus;
use App\Models\Booklet;
use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Models\Season;
use App\Models\SeasonAttendance;
use App\Models\SeasonEvent;
use App\Models\SeasonEventAttachment;
use App\Models\SeasonMember;
use App\Services\LiturgicalInfoService;
use App\Services\SeasonSharingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The leader's side of a season: its dates, what hangs off each, the link the
 * choir gets, and the grid of who said what.
 *
 * Adding dates is the thing done most, so the form asks for as little as it
 * can — a date and a name — and offers the rest: the day's celebrations from
 * the liturgical calendar, a plan already made for that day, and a weekly
 * repeat for the rehearsals.
 */
class SeasonEditor extends Component
{
    use AuthorizesRequests;

    /** The longest run a weekly repeat may produce, a season's worth of Tuesdays. */
    public const MAX_REPEATS = 60;

    #[Locked]
    public Season $season;

    public string $name = '';

    public string $description = '';

    #[Url(as: 'tab', keep: false)]
    public string $tab = 'events';

    public bool $showPast = false;

    #[Locked]
    public ?int $editingEventId = null;

    public string $eventDate = '';

    public string $eventTime = '';

    public string $eventName = '';

    public string $eventNote = '';

    public string $repeatUntil = '';

    public ?int $celebrationId = null;

    /** The tied plan's id, as the select holds it: '' for none. */
    public string $musicPlanId = '';

    #[Locked]
    public ?int $attachEventId = null;

    public string $bookletSearch = '';

    public string $linkUrl = '';

    public string $linkLabel = '';

    public function mount(Season $season): void
    {
        $this->authorize('update', $season);

        $this->season = $season;
        $this->name = $season->name;
        $this->description = $season->description ?? '';
    }

    public function saveDetails(): void
    {
        $this->authorize('update', $this->season);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->season->update([
            'name' => trim($validated['name']),
            'description' => trim((string) $validated['description']) ?: null,
        ]);

        $this->dispatch('toast', message: __('Saved.'), type: 'success');
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->season);

        $this->season->delete();

        $this->redirectRoute('seasons', navigate: true);
    }

    /**
     * The dates on the page, with everything their rows show.
     *
     * @return Collection<int, SeasonEvent>
     */
    #[Computed]
    public function events(): Collection
    {
        return $this->season->events()
            ->when(! $this->showPast, fn (Builder $query) => $query->whereDate('date', '>=', today()))
            ->with([
                'celebration',
                'musicPlan.celebration',
                'attachments' => fn ($query) => $query->with('loan')->with(['lendable' => fn (MorphTo $morph) => $morph->morphWith([
                    MusicPlan::class => ['celebration'],
                ])]),
                'attendances',
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

    /**
     * Every answer on the page, keyed "event-member", for the grid.
     *
     * @return array<string, AttendanceStatus>
     */
    #[Computed]
    public function answers(): array
    {
        $answers = [];

        foreach ($this->events as $event) {
            foreach ($event->attendances as $attendance) {
                $answers[$event->id.'-'.$attendance->season_member_id] = $attendance->status;
            }
        }

        return $answers;
    }

    #[Computed]
    public function isShared(): bool
    {
        return app(SeasonSharingService::class)->isShared($this->season);
    }

    /**
     * The season link each date's own link is built on: the oldest live one
     * open to anyone holding it — the one most likely already sent round —
     * or, failing that, a link for named people.
     */
    #[Computed]
    public function shareToken(): ?string
    {
        /** @var string|null */
        return $this->season->liveLoans()
            ->orderBy('restricted')
            ->oldest('id')
            ->value('token');
    }

    /**
     * A season link handed out or recalled below: the attachments' own links
     * came or went with it (LoanObserver), so the rows are read again.
     */
    #[On('loans-changed')]
    public function refreshSharing(): void
    {
        unset($this->isShared, $this->events, $this->shareToken);
    }

    /**
     * The celebrations of the liturgical calendar on the date being entered.
     *
     * @return list<array{index: int, name: string, color: string|null}>
     */
    #[Computed]
    public function liturgicalOptions(): array
    {
        $date = $this->validDate($this->eventDate);

        if ($date === null) {
            return [];
        }

        $options = [];

        foreach (app(LiturgicalInfoService::class)->getCelebrations($date->format('Y-m-d')) as $index => $payload) {
            $options[] = [
                'index' => (int) $index,
                'name' => (string) ($payload['name'] ?? $payload['title'] ?? ''),
                'color' => $payload['colorText'] ?? null,
            ];
        }

        return $options;
    }

    #[Computed]
    public function selectedCelebration(): ?Celebration
    {
        return $this->celebrationId === null ? null : Celebration::query()->find($this->celebrationId);
    }

    /**
     * The leader's plans a date can be tied to: the ones for that very day
     * first, then the most recent.
     *
     * @return \Illuminate\Support\Collection<int, MusicPlan>
     */
    #[Computed]
    public function selectablePlans(): \Illuminate\Support\Collection
    {
        $date = $this->validDate($this->eventDate);

        $sameDay = $date === null ? collect() : MusicPlan::query()
            ->where('user_id', Auth::id())
            ->whereHas('celebration', fn (Builder $query) => $query->whereDate('actual_date', $date))
            ->with('celebration')
            ->get();

        $recent = MusicPlan::query()
            ->where('user_id', Auth::id())
            ->with('celebration')
            ->latest('updated_at')
            ->limit(40)
            ->get();

        $current = $this->musicPlanId === '' ? collect() : MusicPlan::query()
            ->where('user_id', Auth::id())
            ->whereKey((int) $this->musicPlanId)
            ->with('celebration')
            ->get();

        return $sameDay->concat($current)->concat($recent)->unique('id')->values();
    }

    public function updatedEventDate(): void
    {
        unset($this->liturgicalOptions, $this->selectablePlans);
    }

    public function newEvent(): void
    {
        $this->resetEventForm();

        $this->modal('season-event')->show();
    }

    public function editEvent(int $eventId): void
    {
        $event = $this->findEvent($eventId);

        $this->resetEventForm();
        $this->editingEventId = $event->id;
        $this->eventDate = $event->date->format('Y-m-d');
        $this->eventTime = $event->timeLabel() ?? '';
        $this->eventName = $event->name;
        $this->eventNote = $event->note ?? '';
        $this->celebrationId = $event->celebration_id;
        $this->musicPlanId = (string) ($event->music_plan_id ?? '');

        $this->modal('season-event')->show();
    }

    /**
     * Tie the date to one of the day's celebrations, naming it after the
     * celebration when no name was typed yet.
     */
    public function chooseCelebration(int $index): void
    {
        $date = $this->validDate($this->eventDate);

        if ($date === null) {
            return;
        }

        $payload = app(LiturgicalInfoService::class)->getCelebrations($date->format('Y-m-d'))[$index] ?? null;

        if (! is_array($payload)) {
            return;
        }

        $celebration = Celebration::fromLiturgicalPayload($payload, $date->format('Y-m-d'));

        $this->celebrationId = $celebration->id;

        if (trim($this->eventName) === '') {
            $this->eventName = $celebration->name;
        }

        unset($this->selectedCelebration);
    }

    public function clearCelebration(): void
    {
        $this->celebrationId = null;

        unset($this->selectedCelebration);
    }

    public function saveEvent(): void
    {
        $this->authorize('update', $this->season);

        $validated = $this->validate([
            'eventDate' => ['required', 'date_format:Y-m-d'],
            'eventTime' => ['nullable', 'date_format:H:i'],
            'eventName' => ['required', 'string', 'max:150'],
            'eventNote' => ['nullable', 'string', 'max:2000'],
            'repeatUntil' => $this->editingEventId === null
                ? ['nullable', 'date_format:Y-m-d', 'after:eventDate', 'before_or_equal:'.CarbonImmutable::parse($this->validDate($this->eventDate) ?? today())->addYear()->format('Y-m-d')]
                : ['prohibited'],
            'celebrationId' => ['nullable', 'integer', Rule::exists('celebrations', 'id')],
            'musicPlanId' => ['nullable', 'integer', Rule::exists('music_plans', 'id')->where('user_id', Auth::id())],
        ]);

        $attributes = [
            'name' => trim($validated['eventName']),
            'time' => $validated['eventTime'] ?: null,
            'note' => trim((string) $validated['eventNote']) ?: null,
            'celebration_id' => $validated['celebrationId'],
            'music_plan_id' => $validated['musicPlanId'] ? (int) $validated['musicPlanId'] : null,
        ];

        if ($this->editingEventId !== null) {
            $this->findEvent($this->editingEventId)->update([...$attributes, 'date' => $validated['eventDate']]);
        } else {
            foreach ($this->occurrences($validated['eventDate'], $validated['repeatUntil'] ?: null) as $date) {
                $this->season->events()->create([...$attributes, 'date' => $date]);
            }
        }

        $this->resetEventForm();
        $this->modal('season-event')->close();

        unset($this->events, $this->pastCount);
    }

    public function deleteEvent(int $eventId): void
    {
        $this->findEvent($eventId)->delete();

        unset($this->events, $this->pastCount);
    }

    public function openAttach(int $eventId): void
    {
        $this->attachEventId = $this->findEvent($eventId)->id;
        $this->bookletSearch = '';
        $this->linkUrl = '';
        $this->linkLabel = '';
        $this->resetValidation();

        unset($this->attachEvent, $this->attachableBooklets);

        $this->modal('season-attach')->show();
    }

    #[Computed]
    public function attachEvent(): ?SeasonEvent
    {
        return $this->attachEventId === null ? null : $this->findEvent($this->attachEventId)->load('musicPlan.celebration', 'attachments');
    }

    /**
     * The leader's booklets, those made from the date's own plan first.
     *
     * @return Collection<int, Booklet>
     */
    #[Computed]
    public function attachableBooklets(): Collection
    {
        $planId = $this->attachEvent?->music_plan_id;
        $search = trim($this->bookletSearch);

        return Booklet::query()
            ->mine(Auth::user())
            ->with('musicPlan.celebration')
            ->when($search !== '', fn (Builder $query) => $query->where('title', 'ilike', "%{$search}%"))
            ->when($planId !== null, fn (Builder $query) => $query->orderByRaw('music_plan_id = ? desc nulls last', [$planId]))
            ->latest('updated_at')
            ->limit(30)
            ->get();
    }

    public function attachBooklet(int $bookletId): void
    {
        $event = $this->requireAttachEvent();
        $booklet = Booklet::query()->mine(Auth::user())->findOrFail($bookletId);

        app(SeasonSharingService::class)->attach($event, $booklet);

        $this->attached();
    }

    public function attachPlan(): void
    {
        $event = $this->requireAttachEvent();

        abort_if($event->music_plan_id === null, 404);

        $plan = MusicPlan::query()->where('user_id', Auth::id())->findOrFail($event->music_plan_id);

        app(SeasonSharingService::class)->attach($event, $plan);

        $this->attached();
    }

    public function attachLink(): void
    {
        $event = $this->requireAttachEvent();

        $validated = $this->validate([
            'linkUrl' => ['required', 'string', 'max:2048', 'url:http,https'],
            'linkLabel' => ['nullable', 'string', 'max:150'],
        ]);

        app(SeasonSharingService::class)->attachLink($event, $validated['linkUrl'], $validated['linkLabel']);

        $this->linkUrl = '';
        $this->linkLabel = '';

        $this->attached();
    }

    public function detach(int $attachmentId): void
    {
        $this->authorize('update', $this->season);

        /** @var SeasonEventAttachment $attachment */
        $attachment = $this->season->attachments()->findOrFail($attachmentId);

        app(SeasonSharingService::class)->detach($attachment);

        unset($this->events, $this->attachEvent);
    }

    /**
     * Step one member's answer for one date round: nothing, yes, maybe, no.
     *
     * For the answers given at the rehearsal in person rather than on the link.
     */
    public function cycleAnswer(int $eventId, int $memberId): void
    {
        $event = $this->findEvent($eventId);
        $member = $this->findMember($memberId);

        $attendance = SeasonAttendance::query()
            ->where('season_event_id', $event->id)
            ->where('season_member_id', $member->id)
            ->first();

        $next = AttendanceStatus::after($attendance?->status);

        if ($next === null) {
            $attendance?->delete();
        } else {
            SeasonAttendance::query()->updateOrCreate(
                ['season_event_id' => $event->id, 'season_member_id' => $member->id],
                ['status' => $next],
            );
        }

        unset($this->events, $this->answers);
    }

    public function addMember(string $name): void
    {
        $this->authorize('update', $this->season);

        $name = trim(mb_substr($name, 0, 60));

        if ($name === '') {
            return;
        }

        $this->season->members()->firstOrCreate(['name' => $name]);

        unset($this->members);
    }

    public function renameMember(int $memberId, string $name): void
    {
        $member = $this->findMember($memberId);
        $name = trim(mb_substr($name, 0, 60));

        if ($name === '' || $name === $member->name) {
            return;
        }

        if ($this->season->members()->where('name', $name)->exists()) {
            $this->dispatch('toast', message: __('There is already a member called :name.', ['name' => $name]), type: 'danger');

            unset($this->members);

            return;
        }

        $member->update(['name' => $name]);

        unset($this->members);
    }

    public function deleteMember(int $memberId): void
    {
        $this->findMember($memberId)->delete();

        unset($this->members, $this->events, $this->answers);
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', [
            'title' => $this->season->name,
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.season-editor');
    }

    /**
     * The dates a new event lands on: the one given, and every seventh day
     * after it up to the repeat's end.
     *
     * @return list<string>
     */
    private function occurrences(string $date, ?string $until): array
    {
        $current = CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay();
        $dates = [$current->format('Y-m-d')];

        if ($until === null) {
            return $dates;
        }

        $end = CarbonImmutable::createFromFormat('Y-m-d', $until)->startOfDay();

        while (count($dates) < self::MAX_REPEATS) {
            $current = $current->addWeek();

            if ($current->greaterThan($end)) {
                break;
            }

            $dates[] = $current->format('Y-m-d');
        }

        return $dates;
    }

    private function validDate(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function resetEventForm(): void
    {
        $this->reset(['editingEventId', 'eventDate', 'eventTime', 'eventName', 'eventNote', 'repeatUntil', 'celebrationId', 'musicPlanId']);
        $this->resetValidation();

        unset($this->liturgicalOptions, $this->selectablePlans, $this->selectedCelebration);
    }

    private function attached(): void
    {
        unset($this->events, $this->attachEvent);

        $this->dispatch('toast', message: __('Attached.'), type: 'success');
    }

    private function requireAttachEvent(): SeasonEvent
    {
        abort_if($this->attachEventId === null, 404);

        return $this->findEvent($this->attachEventId);
    }

    private function findEvent(int $eventId): SeasonEvent
    {
        $this->authorize('update', $this->season);

        /** @var SeasonEvent */
        return $this->season->events()->findOrFail($eventId);
    }

    private function findMember(int $memberId): SeasonMember
    {
        $this->authorize('update', $this->season);

        /** @var SeasonMember */
        return $this->season->members()->findOrFail($memberId);
    }
}
