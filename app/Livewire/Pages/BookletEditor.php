<?php

namespace App\Livewire\Pages;

use App\Enums\BookletOrientation;
use App\Enums\BookletPageSize;
use App\Models\Booklet;
use App\Models\BookletScore;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlotAssignment;
use App\Services\MusicPlanScoreListService;
use App\Support\BookletSettingFields;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Choosing on the left, pages on the right.
 *
 * The component owns the choosing: which scores are in, in what order, what is
 * said above each of them, and what had to be nudged to make each one sit well.
 * It owns none of the drawing — every page is engraved in the browser from the
 * scores themselves, because that is where the four renderers live and because
 * nothing about a booklet is worth storing as a picture.
 */
class BookletEditor extends Component
{
    use AuthorizesRequests;

    public Booklet $booklet;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('required|string')]
    public string $pageSize = 'a5';

    #[Validate('required|string')]
    public string $orientation = 'portrait';

    #[Validate('required|numeric|min:0|max:60')]
    public float $marginMm = 12;

    #[Validate('required|numeric|min:5|max:24')]
    public float $lyricSizePt = 11;

    #[Validate('required|numeric|min:2|max:20')]
    public float $staffHeightMm = 7;

    public bool $showTitles = true;

    /**
     * The entry whose settings the override panel is open on.
     */
    public ?int $editingEntryId = null;

    /**
     * The text entry being written, and its Markdown while it is being written.
     */
    public ?int $editingTextId = null;

    #[Validate('nullable|string|max:20000')]
    public string $editingText = '';

    public function mount(Booklet $booklet): void
    {
        $this->authorize('update', $booklet);

        $this->booklet = $booklet;
        $this->title = $booklet->title;
        $this->pageSize = $booklet->page_size->value;
        $this->orientation = $booklet->orientation->value;
        $this->marginMm = $booklet->margin_mm;
        $this->lyricSizePt = $booklet->lyric_size_pt;
        $this->staffHeightMm = $booklet->staff_height_mm;
        $this->showTitles = $booklet->show_titles;
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', ['title' => $this->booklet->title]);
    }

    /**
     * The plan's slots, in liturgical order, with every score the viewer may see
     * for each music — the same list the service view shows, reused whole.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function planSlots(): array
    {
        $plan = $this->booklet->musicPlan;

        if (! $plan instanceof MusicPlan) {
            return [];
        }

        $viewer = Auth::user();
        $scoresByMusicId = app(MusicPlanScoreListService::class)->forViewer($plan, $viewer);

        $assignments = $plan->musicAssignments()
            ->with(['music'])
            ->orderBy('music_plan_slot_plan_id')
            ->orderBy('music_sequence')
            ->get()
            ->groupBy('music_plan_slot_plan_id');

        return $plan->slots()
            ->withPivot('id', 'sequence')
            ->orderBy('music_plan_slot_plan.sequence')
            ->get()
            ->map(fn ($slot): array => [
                'id' => $slot->id,
                'name' => $slot->name,
                'assignments' => $assignments->get($slot->pivot->id, collect())
                    ->map(fn ($assignment): array => [
                        'id' => $assignment->id,
                        'music_title' => $assignment->music?->title,
                        'scores' => $scoresByMusicId->get($assignment->music_id, collect())->all(),
                    ])
                    ->all(),
            ])
            ->all();
    }

    /**
     * The chosen entries, in order.
     *
     * @return Collection<int, BookletScore>
     */
    #[Computed]
    public function entries(): Collection
    {
        return $this->booklet->entries()->with(['score', 'assignment.music', 'assignment.musicPlanSlot'])->get();
    }

    /**
     * What the browser needs to draw the booklet.
     *
     * The sources come from MusicPlanScoreListService, so a score reaches the
     * page exactly when the viewer may read it — and stops reaching it the moment
     * a loan is recalled, since this is resolved afresh on every render.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function renderPayload(): array
    {
        $entries = $this->entries();
        $headings = $this->headings();

        $sources = app(MusicPlanScoreListService::class)
            ->sourcesFor($entries->whereNotNull('score_id')->pluck('score_id')->all(), Auth::user());

        return $entries
            ->map(function (BookletScore $entry) use ($sources, $headings): ?array {
                $heading = $headings[$entry->id] ?? ['slot' => null, 'music' => null, 'variation' => null];

                if ($entry->isText()) {
                    return [
                        'id' => $entry->id,
                        'kind' => 'text',
                        'text' => $entry->text ?? '',
                        'startOnNewPage' => $entry->start_on_new_page,
                    ];
                }

                $source = $sources->get($entry->score_id);

                if ($source === null) {
                    return null;
                }

                return [
                    'id' => $entry->id,
                    'kind' => 'score',
                    'scoreId' => $entry->score_id,
                    'slot' => $heading['slot'],
                    'music' => $heading['music'],
                    'variation' => $heading['variation'],
                    'format' => $source['format'],
                    'content' => $source['content'],
                    'settings' => $source['settings'],
                    'override' => $entry->settings_override ?? [],
                    'startOnNewPage' => $entry->start_on_new_page,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * What is printed above each entry, resolved from the plan rather than stored.
     *
     * A booklet names the moment in the service, not the engraving: the slot is
     * the heading, and it is announced once, so a slot with three scores under it
     * is not announced three times — a paragraph of instructions in between is
     * not a score and does not start the naming over. The music's own name is
     * off until someone asks for it on that row. It then joins the slot on its
     * line only where the slot holds a single music and there is nothing to tell
     * apart; where the slot holds several, every one of them takes a line of its
     * own beneath the slot's, so they read as the list they are rather than the
     * first being promoted into the heading.
     *
     * @return array<int, array{slot: ?string, music: ?string, variation: ?string}>
     */
    #[Computed]
    public function headings(): array
    {
        $entries = $this->entries();
        $assignments = $this->assignmentsFor($entries);
        $musicCounts = $this->musicCountsPerSlot($entries, $assignments);

        $lines = [];
        $lastSlotKey = null;

        foreach ($entries as $entry) {
            if ($entry->isText()) {
                $lines[$entry->id] = ['slot' => null, 'music' => null, 'variation' => null];

                // Words between the music say nothing about the plan, so they
                // neither carry a heading nor make the next score repeat one.
                continue;
            }

            $assignment = $assignments->get($entry->music_plan_slot_assignment_id);
            $slotKey = $assignment?->music_plan_slot_plan_id;
            $slotName = $assignment?->musicPlanSlot?->name;
            $musicTitle = $entry->show_music_title ? $assignment?->music?->title : null;

            $slotLine = null;

            if ($assignment === null) {
                // Chosen outside the plan, or from an assignment since removed:
                // the score speaks for itself.
                $slotLine = $entry->score?->title;
            } elseif ($slotKey !== $lastSlotKey || $slotKey === null) {
                $slotLine = $slotName;
            }

            $alone = $slotKey !== null && ($musicCounts[$slotKey] ?? 0) <= 1;

            if ($slotLine !== null && $musicTitle !== null && $alone) {
                $slotLine = implode(' – ', [$slotLine, $musicTitle]);
                $musicTitle = null;
            }

            $lines[$entry->id] = [
                'slot' => $slotLine,
                'music' => $musicTitle,
                'variation' => $entry->show_variation ? $entry->score?->variationLabel() : null,
            ];

            $lastSlotKey = $slotKey;
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function geometry(): array
    {
        return $this->booklet->geometry();
    }

    /**
     * The score ids already in the booklet, for ticking the list.
     *
     * @return list<int>
     */
    #[Computed]
    public function chosenScoreIds(): array
    {
        return $this->entries()->whereNotNull('score_id')->pluck('score_id')->all();
    }

    public function updated(string $property): void
    {
        if ($property === 'editingText') {
            $this->saveText();

            return;
        }

        if (! in_array($property, ['title', 'pageSize', 'orientation', 'marginMm', 'lyricSizePt', 'staffHeightMm', 'showTitles'], true)) {
            return;
        }

        $this->saveGeometry();
    }

    public function saveGeometry(): void
    {
        $this->authorize('update', $this->booklet);
        $this->validate();

        $this->booklet->update([
            'title' => $this->title,
            'page_size' => BookletPageSize::from($this->pageSize),
            'orientation' => BookletOrientation::from($this->orientation),
            'margin_mm' => $this->marginMm,
            'lyric_size_pt' => $this->lyricSizePt,
            'staff_height_mm' => $this->staffHeightMm,
            'show_titles' => $this->showTitles,
        ]);

        unset($this->geometry);

        $this->forgetEntries();
    }

    /**
     * Add or remove one score.
     *
     * Adding checks that the viewer may actually read it, so a score id typed
     * into a request cannot pull someone else's work into a booklet. The
     * assignment it was chosen from rides along, because that — not the score —
     * is what names it on the page, and it is also what says where the score
     * lands: with its own slot, rather than at the end.
     */
    public function toggleScore(int $scoreId, ?int $assignmentId = null): void
    {
        $this->authorize('update', $this->booklet);

        $existing = $this->booklet->entries()->where('score_id', $scoreId)->first();

        if ($existing instanceof BookletScore) {
            $this->removeEntry($existing->id);

            return;
        }

        $readable = app(MusicPlanScoreListService::class)->sourcesFor([$scoreId], Auth::user());

        if (! $readable->has($scoreId)) {
            return;
        }

        $assignment = $this->assignmentInPlan($assignmentId);

        $entry = $this->booklet->entries()->create([
            'score_id' => $scoreId,
            'music_plan_slot_assignment_id' => $assignment?->id,
            'sequence' => (int) $this->booklet->entries()->max('sequence') + 1,
        ]);

        $order = $this->orderJoiningSlot($entry->id, $assignment?->music_plan_slot_plan_id);

        if ($order !== null) {
            $this->applyOrder($order);

            return;
        }

        $this->forgetEntries();
    }

    /**
     * Add a paragraph of instructions — words the booklet says rather than sings.
     */
    public function addText(): void
    {
        $this->authorize('update', $this->booklet);

        $entry = $this->booklet->entries()->create([
            'text' => '',
            'sequence' => (int) $this->booklet->entries()->max('sequence') + 1,
        ]);

        $this->editingTextId = $entry->id;
        $this->editingText = '';

        $this->forgetEntries();
    }

    public function editText(?int $entryId): void
    {
        $this->editingTextId = null;
        $this->editingText = '';

        if ($entryId === null) {
            return;
        }

        $entry = $this->booklet->entries()->whereNull('score_id')->find($entryId);

        if ($entry instanceof BookletScore) {
            $this->editingTextId = $entry->id;
            $this->editingText = $entry->text ?? '';
        }
    }

    public function saveText(): void
    {
        $this->authorize('update', $this->booklet);
        $this->validateOnly('editingText');

        $entry = $this->editingTextId === null
            ? null
            : $this->booklet->entries()->whereNull('score_id')->find($this->editingTextId);

        if (! $entry instanceof BookletScore) {
            return;
        }

        $entry->update(['text' => $this->editingText]);

        $this->forgetEntries();
    }

    public function removeEntry(int $entryId): void
    {
        $this->authorize('update', $this->booklet);

        $entry = $this->booklet->entries()->find($entryId);

        if (! $entry instanceof BookletScore) {
            return;
        }

        $entry->delete();

        if ($this->editingEntryId === $entryId) {
            $this->editingEntryId = null;
        }

        if ($this->editingTextId === $entryId) {
            $this->editText(null);
        }

        $this->forgetEntries();
    }

    public function move(int $entryId, int $direction): void
    {
        $this->authorize('update', $this->booklet);

        $ordered = $this->booklet->entries()->get()->pluck('id')->all();
        $index = array_search($entryId, $ordered, true);

        if ($index === false) {
            return;
        }

        $target = $index + ($direction < 0 ? -1 : 1);

        if ($target < 0 || $target >= count($ordered)) {
            return;
        }

        array_splice($ordered, $target, 0, array_splice($ordered, $index, 1));

        $this->applyOrder($ordered);
    }

    /**
     * Put the entries in the given order.
     *
     * Sequences are rewritten from scratch, so a list that had drifted out of
     * step — a deletion, an older booklet — comes back in order.
     *
     * @param  list<int>  $entryIds
     */
    private function applyOrder(array $entryIds): void
    {
        $entries = $this->booklet->entries()->get()->keyBy('id');

        foreach ($entryIds as $position => $id) {
            $entries[$id]->update(['sequence' => $position]);
        }

        $this->forgetEntries();
    }

    public function toggleStartOnNewPage(int $entryId): void
    {
        $this->flip($entryId, 'start_on_new_page');
    }

    public function toggleShowVariation(int $entryId): void
    {
        $this->flip($entryId, 'show_variation');
    }

    public function toggleShowMusicTitle(int $entryId): void
    {
        $this->flip($entryId, 'show_music_title');
    }

    public function editSettings(?int $entryId): void
    {
        $this->editingEntryId = $entryId;
    }

    /**
     * Store one score's hand-made adjustments for this booklet.
     *
     * Sanitised against BookletSettingFields rather than trusted: the bucket is
     * arbitrary JSON from a browser, and it is replayed into a renderer.
     *
     * @param  array<string, mixed>  $override
     */
    public function saveOverride(int $entryId, array $override): void
    {
        $this->authorize('update', $this->booklet);

        $entry = $this->booklet->entries()->with('score')->find($entryId);

        if (! $entry instanceof BookletScore || $entry->isText()) {
            return;
        }

        $clean = BookletSettingFields::sanitize($entry->score?->format?->value, $override);

        $entry->update(['settings_override' => $clean === [] ? null : $clean]);

        $this->forgetEntries();
    }

    public function resetOverride(int $entryId): void
    {
        $this->saveOverride($entryId, []);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.booklet-editor');
    }

    /**
     * The assignments the chosen entries came from.
     *
     * @param  Collection<int, BookletScore>  $entries
     * @return Collection<int, MusicPlanSlotAssignment>
     */
    private function assignmentsFor(Collection $entries): Collection
    {
        return $entries
            ->pluck('assignment')
            ->filter()
            ->keyBy('id');
    }

    /**
     * How many musics each slot holds in this booklet, which decides whether a
     * slot heading may carry the music's name itself.
     *
     * Counted from what was chosen rather than from the plan: a slot the plan
     * fills with three musics but the booklet takes one of is, on the page, a
     * slot with one music, and reads better named on one line.
     *
     * @param  Collection<int, BookletScore>  $entries
     * @param  Collection<int, MusicPlanSlotAssignment>  $assignments
     * @return array<int, int>
     */
    private function musicCountsPerSlot(Collection $entries, Collection $assignments): array
    {
        return $entries
            ->map(fn (BookletScore $entry): ?MusicPlanSlotAssignment => $assignments->get($entry->music_plan_slot_assignment_id))
            ->filter()
            ->groupBy('music_plan_slot_plan_id')
            ->map(fn (Collection $group): int => $group->pluck('music_id')->unique()->count())
            ->all();
    }

    /**
     * The assignment, but only when it really belongs to this booklet's plan —
     * a heading is read by whoever holds the booklet, and must not be borrowed
     * from someone else's service.
     */
    private function assignmentInPlan(?int $assignmentId): ?MusicPlanSlotAssignment
    {
        if ($assignmentId === null || $this->booklet->music_plan_id === null) {
            return null;
        }

        return MusicPlanSlotAssignment::query()
            ->where('id', $assignmentId)
            ->whereHas('musicPlanSlotPlan', fn ($query) => $query->where('music_plan_id', $this->booklet->music_plan_id))
            ->first();
    }

    /**
     * Where a newly chosen score belongs: with the slot it was chosen from.
     *
     * A booklet is read as a service, so a second score for a slot the booklet
     * already says joins that slot rather than landing at the end: it is added
     * last under the slot's first appearance — first, because the same slot may
     * be sung at more than one point and the earliest is the one being filled
     * out — and the slot ends where the next one begins. Everything already
     * standing there stays where it was put, words included, so a paragraph
     * written under the slot keeps whatever it was written beneath.
     *
     * @return list<int>|null the ids in their new order, or null to leave the entry at the end
     */
    private function orderJoiningSlot(int $entryId, ?int $slotPlanId): ?array
    {
        if ($slotPlanId === null) {
            return null;
        }

        $others = $this->booklet->entries()
            ->with('assignment')
            ->get()
            ->reject(fn (BookletScore $entry): bool => $entry->id === $entryId)
            ->values();

        $joined = false;
        $nextSlotStarts = null;

        foreach ($others as $index => $entry) {
            $entrySlotPlanId = $entry->assignment?->music_plan_slot_plan_id;

            if ($entrySlotPlanId === $slotPlanId) {
                $joined = true;

                continue;
            }

            if ($joined && $entrySlotPlanId !== null) {
                $nextSlotStarts = $index;

                break;
            }
        }

        if (! $joined) {
            return null;
        }

        $ids = $others->pluck('id')->all();
        array_splice($ids, $nextSlotStarts ?? count($ids), 0, [$entryId]);

        return $ids;
    }

    private function flip(int $entryId, string $column): void
    {
        $this->authorize('update', $this->booklet);

        $entry = $this->booklet->entries()->find($entryId);

        if ($entry instanceof BookletScore) {
            $entry->update([$column => ! $entry->{$column}]);
            $this->forgetEntries();
        }
    }

    /**
     * Hand the browser a fresh picture of the booklet.
     *
     * Pushed rather than pulled: the payload is a computed property, which lives
     * only on the server, so there is nothing for the Alpine half to read back.
     * Sending it on each change also keeps the score content out of every
     * request body, which is where it would end up if this were public state.
     */
    private function forgetEntries(): void
    {
        $this->booklet->unsetRelation('entries');
        unset($this->entries, $this->renderPayload, $this->chosenScoreIds, $this->headings);

        $this->dispatch(
            'booklet-updated',
            payload: $this->renderPayload,
            geometry: $this->geometry,
        );
    }
}
