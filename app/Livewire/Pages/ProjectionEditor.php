<?php

namespace App\Livewire\Pages;

use App\Enums\ProjectionRatio;
use App\Enums\ProjectionTextTheme;
use App\Facades\GenreContext;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Services\MusicPlanScoreListService;
use App\Services\PlanOutline;
use App\Services\ProjectionRenderPayload;
use App\Support\ProjectionSettingFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * The plan on the left, the slides on the right.
 *
 * The projection's editor, and deliberately the booklet editor's twin: the
 * choosing is the same work — which scores are in, in what order, what is said
 * above each of them — done on the plan itself rather than beside it, so a deck's
 * shape is the service's shape. PlanOutline puts the two together and answers for
 * both documents.
 *
 * What it owns is none of the drawing. Every slide is engraved in the browser
 * from the score itself, at the score author's own layout for this ratio, and cut
 * where that author put the page breaks — which is why the ratio is very nearly
 * the only setting here. A booklet has a page to describe and a pile of
 * differently-engraved scores to unify onto it; a projection has a screen shape
 * and scores already tuned for it.
 */
class ProjectionEditor extends Component
{
    use AuthorizesRequests;

    public Projection $projection;

    #[Validate('required|string|max:255')]
    public string $title = '';

    /**
     * The shape of the screen, and very nearly the whole of this deck's
     * geometry. Changing it does not restyle anything: it changes which of the
     * score's own saved layouts is read, and which page breaks cut.
     */
    public string $ratio = '16/9';

    /**
     * How this deck sets a screen of words.
     *
     * The one thing here that is a look rather than a shape, and it is a
     * deck-wide answer on purpose: a projection whose text slides changed colour
     * halfway through reads as broken rather than as considered. It says nothing
     * about the music, which three engines draw in ink and always will.
     */
    public string $textTheme = 'dark';

    /**
     * How large a screen of words is set, and how far apart its lines stand.
     *
     * A factor of the size the slide computes from its own height rather than a
     * size in points: the canvas is the shape of the screen, and a deck asked
     * for sixteen points would mean something different at every ratio. Deck
     * wide like the theme, and for the same reason — but a row that needs to be
     * larger or smaller than the rest still says so on its own panel.
     */
    #[Validate('required|numeric|min:0.3|max:4')]
    public float $textSizeScale = 1.0;

    #[Validate('required|numeric|min:0.8|max:3')]
    public float $textLineHeight = 1.45;

    /**
     * The words just added, so that they open ready to be written in.
     *
     * Which panels a row has open is the row's own business, and a row is only
     * told anything when it is first drawn — so this is how a brand new one is
     * handed the news that it is new.
     */
    public ?int $openedTextId = null;

    /**
     * The celebration being looked for in the plan picker.
     *
     * Only ever shown to a deck started without a plan: one that has a service
     * behind it is choosing from that service, not from a list of them.
     */
    public string $planSearch = '';

    public function mount(Projection $projection): void
    {
        $this->authorize('update', $projection);

        $this->projection = $projection;
        $this->title = $projection->title;
        $this->ratio = $projection->ratio->value;
        $this->textTheme = $projection->text_theme->value;
        $this->textSizeScale = $projection->text_size_scale;
        $this->textLineHeight = $projection->text_line_height;

        $this->normalizeOrder();
    }

    /**
     * The one rule that cannot be stated as an attribute: a deck is one of the
     * three shapes a projector throws, and that list lives in ProjectionRatio.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ratio' => ['required', 'string', Rule::in(array_keys(ProjectionRatio::options()))],
            'textTheme' => ['required', 'string', Rule::in(array_keys(ProjectionTextTheme::options()))],
        ];
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', ['title' => $this->projection->title]);
    }

    /**
     * Save what the toolbar changed.
     *
     * A whitelist rather than everything, because the search box and the
     * just-opened-text marker are state rather than settings.
     */
    public function updated(string $property): void
    {
        if (! in_array($property, ['title', 'ratio', 'textTheme', 'textSizeScale', 'textLineHeight'], true)) {
            return;
        }

        $this->save();
    }

    public function save(): void
    {
        $this->authorize('update', $this->projection);
        $this->validate();

        $this->projection->update([
            'title' => $this->title,
            'ratio' => ProjectionRatio::from($this->ratio),
            'text_theme' => ProjectionTextTheme::from($this->textTheme),
            'text_size_scale' => $this->textSizeScale,
            'text_line_height' => $this->textLineHeight,
        ]);

        unset($this->geometry);

        $this->forgetEntries();
    }

    /**
     * The deck as the plan it was made from: slots, their music, and what of it
     * was taken.
     *
     * This is the whole left-hand pane, and it is also the order the slides run
     * in — walking it is what the sequences are written from, so the pane and the
     * screen can never say different things.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function outline(): array
    {
        return app(PlanOutline::class)->for(
            $this->projection,
            $this->entries,
            $this->chosenScoreIds,
            $this->chosenFileIds,
        );
    }

    /**
     * The chosen rows, in order.
     *
     * @return Collection<int, ProjectionSlide>
     */
    #[Computed]
    public function entries(): Collection
    {
        return app(ProjectionRenderPayload::class)->entriesOf($this->projection);
    }

    /**
     * What the browser needs to draw the deck.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function renderPayload(): array
    {
        return app(ProjectionRenderPayload::class)->entries(
            $this->projection,
            $this->entries,
            $this->entrySources,
            $this->headings,
        );
    }

    /**
     * Which slides the service walks past, row by row.
     *
     * Kept apart from the render payload on purpose: skipping a verse changes
     * what is shown and nothing about what is drawn, and folding it into a row
     * would have the browser re-engrave the whole deck for every click.
     *
     * @return array<int, list<int>>
     */
    #[Computed]
    public function excluded(): array
    {
        return app(ProjectionRenderPayload::class)->exclusions($this->projection, $this->entries);
    }

    /**
     * What is named above each row, resolved from the plan rather than stored.
     *
     * @return array<int, array{slot: ?string, music: ?string, reference: ?string, variation: ?string}>
     */
    #[Computed]
    public function headings(): array
    {
        return app(ProjectionRenderPayload::class)->headingsFor($this->entries, Auth::user());
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function geometry(): array
    {
        return $this->projection->geometry();
    }

    /**
     * The score ids already on the screen, for ticking the list.
     *
     * @return list<int>
     */
    #[Computed]
    public function chosenScoreIds(): array
    {
        return $this->entries->whereNotNull('score_id')->pluck('score_id')->all();
    }

    /**
     * The typed source of each score in the deck, resolved once per render: both
     * the slides and the ticks in the list are drawn from it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function entrySources(): Collection
    {
        return app(ProjectionRenderPayload::class)->sourcesFor($this->entries, Auth::user());
    }

    /**
     * The uploaded files already in the deck, for ticking a score that offers
     * more than one of them.
     *
     * @return list<int>
     */
    #[Computed]
    public function chosenFileIds(): array
    {
        $sources = $this->entrySources;

        return $this->entries
            ->whereNotNull('score_id')
            ->map(function (ProjectionSlide $entry) use ($sources): ?int {
                $source = $sources->get($entry->score_id);

                return $source === null ? null : app(ProjectionRenderPayload::class)->fileOf($entry, $source)['file_id'];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The plans this deck could be given, when it was started without one.
     *
     * @return Collection<int, MusicPlan>
     */
    #[Computed]
    public function selectablePlans(): Collection
    {
        $search = trim($this->planSearch);

        return MusicPlan::query()
            ->where('user_id', Auth::id())
            ->with('celebration')
            ->when($search !== '', fn (Builder $query) => $query->whereHas(
                'celebration',
                fn (Builder $celebration) => $celebration->where('name', 'ilike', "%{$search}%")
            ))
            ->latest('created_at')
            ->limit(25)
            ->get();
    }

    /**
     * Give a deck that was started from nothing the service it is for.
     */
    public function attachPlan(int $planId): void
    {
        $this->authorize('update', $this->projection);
        $this->ensureNoPlanYet();

        $plan = MusicPlan::query()->findOrFail($planId);
        abort_unless(Gate::allows('view', $plan), 403);

        $this->writePlan($plan);

        $this->modal('projection-plan')->close();
    }

    /**
     * Start a service for this deck, where there was none to choose from.
     */
    public function createPlan(): void
    {
        $this->authorize('update', $this->projection);
        $this->authorize('create', MusicPlan::class);
        $this->ensureNoPlanYet();

        $plan = MusicPlan::create([
            'user_id' => Auth::id(),
            'genre_id' => GenreContext::getId(),
        ]);

        $plan->createCustomCelebration('Egyedi ünnep');

        $this->writePlan($plan);

        $this->modal('projection-plan')->close();

        $this->dispatch('toast', message: __('Music plan created.'), type: 'success');
    }

    /** A plan is given once and kept: a deck cannot be moved between services. */
    private function ensureNoPlanYet(): void
    {
        abort_unless($this->projection->music_plan_id === null, 403);
    }

    private function writePlan(MusicPlan $plan): void
    {
        $this->projection->music_plan_id = $plan->getKey();

        if ($this->projection->title === __('Projection')) {
            $this->projection->title = Projection::titleFor($plan);
            $this->title = $this->projection->title;
        }

        $this->projection->save();
        $this->projection->setRelation('musicPlan', $plan);

        $this->planSearch = '';

        $this->forgetEntries();
    }

    /**
     * Add or remove one score, or one of the files it holds.
     *
     * Adding checks that the viewer may actually read it, so a score id typed
     * into a request cannot pull someone else's work onto a screen, and a file id
     * is honoured only where it is one of that score's own drawable files. The
     * assignment it was chosen from rides along, and the slot with it, because
     * that is what names it and what says where it lands — under its own music,
     * in its own slot, rather than at the end.
     */
    public function toggleScore(int $scoreId, ?int $assignmentId = null, ?int $fileId = null): void
    {
        $this->authorize('update', $this->projection);

        $source = app(MusicPlanScoreListService::class)->sourcesFor([$scoreId], Auth::user())->get($scoreId);

        if ($source === null) {
            // Unreadable now — a recalled loan, an unpublished score. It cannot
            // be added, but one already standing in the deck must still be
            // removable.
            $stale = $this->projection->entries()->where('score_id', $scoreId)->first();

            if ($stale instanceof ProjectionSlide) {
                $this->removeEntry($stale->id);
            }

            return;
        }

        if ($fileId !== null && ! isset($source['files'][$fileId])) {
            return;
        }

        $existing = $this->projection->entries()
            ->where('score_id', $scoreId)
            ->get()
            ->first(fn (ProjectionSlide $entry): bool => app(ProjectionRenderPayload::class)->fileOf($entry, $source)['file_id'] === ($fileId ?? $source['file_id']));

        if ($existing instanceof ProjectionSlide) {
            $this->removeEntry($existing->id);

            return;
        }

        $assignment = $this->assignmentInPlan($assignmentId);

        $order = $this->outlineIds();
        $at = app(PlanOutline::class)->appendIndex(
            $this->outline,
            $assignment?->music_plan_slot_plan_id,
            $assignment?->id,
        );

        $entry = $this->projection->entries()->create([
            'score_id' => $scoreId,
            'score_file_id' => $fileId,
            'music_plan_slot_assignment_id' => $assignment?->id,
            'music_plan_slot_plan_id' => $assignment?->music_plan_slot_plan_id,
            'sequence' => (int) $this->projection->entries()->max('sequence') + 1,
        ]);

        array_splice($order, $at, 0, [$entry->id]);

        $this->applyOrder($order);
    }

    /**
     * Add a screen of words — something the service says rather than sings.
     *
     * Words belong to the moment they introduce, so they are written into the
     * plan like everything else: at the head of a slot, at the head of one of its
     * musics, or straight after a row already standing there. Given none of
     * those, they open the deck.
     */
    public function addText(?int $slotPlanId = null, ?int $assignmentId = null, ?int $afterEntryId = null): void
    {
        $this->authorize('update', $this->projection);

        $assignment = $this->assignmentInPlan($assignmentId);
        $slotPlanId = $assignment?->music_plan_slot_plan_id ?? $this->slotInPlan($slotPlanId);

        $order = $this->outlineIds();
        $at = app(PlanOutline::class)->insertIndex(
            $this->outline,
            $slotPlanId,
            $assignment?->id,
            in_array($afterEntryId, $order, true) ? $afterEntryId : null,
        );

        $entry = $this->projection->entries()->create([
            'text' => '',
            'music_plan_slot_assignment_id' => $assignment?->id,
            'music_plan_slot_plan_id' => $slotPlanId,
            'sequence' => (int) $this->projection->entries()->max('sequence') + 1,
        ]);

        array_splice($order, $at, 0, [$entry->id]);

        $this->openedTextId = $entry->id;

        $this->applyOrder($order);
    }

    /**
     * A row changed something it shows.
     *
     * A row keeps itself; what the slides look like is put together from the
     * whole deck, which only this knows how to do.
     */
    #[On('projection-entry-changed')]
    public function entryChanged(): void
    {
        $this->forgetEntries();
    }

    public function toggleSlotName(int $entryId): void
    {
        $this->toggleHeadingLine($entryId, 'show_slot');
    }

    public function toggleMusicName(int $entryId): void
    {
        $this->toggleHeadingLine($entryId, 'show_music_title');
    }

    public function toggleMusicCollections(int $entryId): void
    {
        $this->toggleHeadingLine($entryId, 'show_collections');
    }

    private function toggleHeadingLine(int $entryId, string $column): void
    {
        $this->authorize('update', $this->projection);

        $entry = $this->projection->entries()->find($entryId);

        if (! $entry instanceof ProjectionSlide) {
            return;
        }

        $entry->update([$column => ! $entry->{$column}]);

        $this->forgetEntries();
    }

    public function removeEntry(int $entryId): void
    {
        $this->authorize('update', $this->projection);

        $entry = $this->projection->entries()->find($entryId);

        if (! $entry instanceof ProjectionSlide) {
            return;
        }

        $entry->delete();

        $this->forgetEntries();
    }

    /**
     * Move one row past the one beside it, inside the music — or the slot, or
     * the deck itself — that it belongs to.
     */
    public function move(int $entryId, int $direction): void
    {
        $this->moveNode('entry', $entryId, $direction);
    }

    public function moveSlot(int $slotPlanId, int $direction): void
    {
        $this->moveNode('slot', $slotPlanId, $direction);
    }

    public function moveMusic(int $assignmentId, int $direction): void
    {
        $this->moveNode('music', $assignmentId, $direction);
    }

    /**
     * Nothing may leave the thing it belongs to, so a move is made on the tree
     * and not on the list.
     */
    private function moveNode(string $kind, int $id, int $direction): void
    {
        $this->authorize('update', $this->projection);

        $outline = app(PlanOutline::class);
        $moved = $outline->moved($this->outline, $kind, $id, $direction);

        if ($moved === null) {
            return;
        }

        $this->applyOrder($outline->flatten($moved));
    }

    /**
     * @return list<int>
     */
    private function outlineIds(): array
    {
        return app(PlanOutline::class)->flatten($this->outline);
    }

    /**
     * Put the deck back into the order the plan reads it in — a deck whose plan
     * has been rearranged since can hold an order no tree would produce.
     */
    private function normalizeOrder(): void
    {
        $ordered = $this->outlineIds();

        if ($ordered === $this->entries->pluck('id')->all()) {
            return;
        }

        $this->writeOrder($ordered);
        $this->forget();
    }

    /**
     * @param  list<int>  $entryIds
     */
    private function applyOrder(array $entryIds): void
    {
        $this->writeOrder($entryIds);

        $this->forgetEntries();
    }

    /**
     * @param  list<int>  $entryIds
     */
    private function writeOrder(array $entryIds): void
    {
        $entries = $this->projection->entries()->get()->keyBy('id');

        foreach ($entryIds as $position => $id) {
            $entries[$id]?->update(['sequence' => $position]);
        }
    }

    /**
     * Store one score's hand-made adjustments for this deck, at this shape.
     *
     * Sanitised against ProjectionSettingFields rather than trusted: the bucket
     * is arbitrary JSON from a browser, and it is replayed into a renderer.
     *
     * What arrives is flat — the browser is drawing one shape and knows only
     * about that one — and it is filed under the shape the deck is currently
     * thrown at, leaving the other two exactly as they were. That is the whole
     * of the scoping: a slide nudged smaller for a square screen in March is
     * still nudged smaller for a square screen in June, and the widescreen deck
     * in between never heard about it.
     *
     * @param  array<string, mixed>  $override
     */
    public function saveOverride(int $entryId, array $override): void
    {
        $this->authorize('update', $this->projection);

        $entry = $this->projection->entries()->with('score')->find($entryId);

        if (! $entry instanceof ProjectionSlide) {
            return;
        }

        $format = self::overrideFormat($entry);
        $clean = ProjectionSettingFields::sanitizeByRatio($format, [
            ...($entry->settings_override ?? []),
            $this->projection->ratio->value => ProjectionSettingFields::sanitize($format, $override),
        ]);

        $entry->update(['settings_override' => $clean === [] ? null : $clean]);

        $this->forgetEntries();
    }

    /**
     * Skip one of the slides a row comes to — or stop skipping it.
     *
     * The slide is still cut, still engraved and still in the contact sheet; the
     * presenter simply walks past it. This is how three of a hymn's six verses
     * are left out on an ordinary Sunday without touching a score that belongs to
     * everyone who sings it.
     *
     * The position is checked for sanity and nothing else. How many slides a row
     * actually comes to is known only to the browser that cut it — and changes
     * under this whenever a `%pagebreak` moves — so an index past the end is
     * harmless rather than wrong: it simply matches no slide.
     */
    public function toggleSlideExclusion(int $entryId, int $index): void
    {
        $this->authorize('update', $this->projection);

        if ($index < 0) {
            return;
        }

        $entry = $this->projection->entries()->find($entryId);

        if (! $entry instanceof ProjectionSlide) {
            return;
        }

        $excluded = $entry->excludedToggled($this->projection->ratio->value, $index);

        $entry->update(['excluded_slides' => $excluded === [] ? null : $excluded]);

        $this->forgetEntries();
    }

    /**
     * Which set of knobs a row answers to.
     */
    public static function overrideFormat(ProjectionSlide $entry): ?string
    {
        if ($entry->isText()) {
            return 'text';
        }

        return $entry->score?->format?->value ?? 'file';
    }

    /** Back to the score author's own layout — at this shape, not at all three. */
    public function resetOverride(int $entryId): void
    {
        $this->saveOverride($entryId, []);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.projection-editor');
    }

    /**
     * The assignment, but only when it really belongs to this deck's plan — a
     * heading is read by a whole congregation, and must not be borrowed from
     * somebody else's service.
     */
    private function assignmentInPlan(?int $assignmentId): ?MusicPlanSlotAssignment
    {
        if ($assignmentId === null || $this->projection->music_plan_id === null) {
            return null;
        }

        return MusicPlanSlotAssignment::query()
            ->where('id', $assignmentId)
            ->whereHas('musicPlanSlotPlan', fn ($query) => $query->where('music_plan_id', $this->projection->music_plan_id))
            ->first();
    }

    private function slotInPlan(?int $slotPlanId): ?int
    {
        if ($slotPlanId === null || $this->projection->music_plan_id === null) {
            return null;
        }

        return MusicPlanSlotPlan::query()
            ->where('id', $slotPlanId)
            ->where('music_plan_id', $this->projection->music_plan_id)
            ->value('id');
    }

    /**
     * Throw away everything read off the deck, so the next question about it is
     * asked of the database.
     */
    private function forget(): void
    {
        $this->projection->unsetRelation('entries');
        unset($this->entries, $this->entrySources, $this->renderPayload, $this->chosenScoreIds, $this->chosenFileIds, $this->headings, $this->outline, $this->excluded);
    }

    /**
     * Hand the browser a fresh picture of the deck.
     *
     * Pushed rather than pulled: the payload is a computed property, which lives
     * only on the server, so there is nothing for the Alpine half to read back.
     * Sending it on each change also keeps the score content out of every request
     * body, which is where it would end up if this were public state.
     */
    private function forgetEntries(): void
    {
        $this->forget();

        $this->dispatch(
            'projection-updated',
            payload: $this->renderPayload,
            geometry: $this->geometry,
            excluded: $this->excluded,
        );
    }
}
