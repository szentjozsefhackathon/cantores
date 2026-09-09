<?php

namespace App\Livewire\Pages;

use App\Enums\BookletOrientation;
use App\Enums\BookletPageSize;
use App\Models\Booklet;
use App\Models\BookletScore;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Services\BookletOutline;
use App\Services\BookletRenderPayload;
use App\Services\MusicPlanScoreListService;
use App\Support\BookletSettingFields;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * The plan on the left, the pages on the right.
 *
 * The component owns the choosing: which scores are in, in what order, what is
 * said above each of them, and what had to be nudged to make each one sit well.
 * It owns none of the drawing — every page is engraved in the browser from the
 * scores themselves, because that is where the four renderers live and because
 * nothing about a booklet is worth storing as a picture.
 *
 * The choosing is done on the plan itself rather than beside it: BookletOutline
 * puts the two together, and the order it reads out of the plan is the order the
 * pages are printed in. So a booklet's shape is the service's shape — a slot may
 * be moved against the plan, a music only inside its slot, a score only inside
 * its music — and the one flat list of sequences is written from the tree rather
 * than kept in step with it by hand.
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
    public float $lyricSizePt = 10.5;

    #[Validate('required|numeric|min:2|max:20')]
    public float $staffHeightMm = 5;

    /**
     * The face everything the booklet writes rather than engraves is set in.
     */
    public string $textFont = 'Inter';

    /**
     * How big a heading is beside the lyrics it stands over.
     */
    #[Validate('required|numeric|min:0.5|max:2')]
    public float $headingScale = 0.9;

    /**
     * How far apart ABC staves stand throughout the booklet.
     *
     * The one format-specific knob on an otherwise format-blind toolbar, and it
     * earns its place: abc2svg reserves this space above the first staff as well
     * as between two of them, so on a booklet page it is both how tightly the
     * music stacks and how close a heading sits to it. A score that needs
     * different can still say so on its own row.
     */
    #[Validate('required|numeric|min:0|max:120')]
    public float $abcStaffSep = 25;

    /**
     * The paragraph just added, so that it opens ready to be written in.
     *
     * Which panels a row has open is the row's own business, and a row is only
     * told anything when it is first drawn — so this is how a brand new one is
     * handed the news that it is new.
     */
    public ?int $openedTextId = null;

    /**
     * The link the band reads this booklet on, when there is one.
     *
     * A booklet is made to be sung from, and the people singing from it are not
     * at the printer. The link hands them the booklet itself rather than a copy
     * of it — engraved afresh on each of their phones, at whatever size they can
     * read — so a chord fixed here at the rehearsal is in their hands on the
     * next refresh. It is read-only and it is recallable, like every other
     * lending link on the site.
     */
    public ?string $shareUrl = null;

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
        $this->textFont = $booklet->text_font;
        $this->headingScale = $booklet->heading_scale;
        $this->abcStaffSep = $booklet->abc_staff_sep;

        $this->shareUrl = $this->urlForToken($booklet->loanToken());

        $this->normalizeOrder();
    }

    /**
     * Hand the booklet to the band.
     *
     * Nothing is copied and nothing is frozen: the link resolves to this booklet
     * on every request, so what it opens is whatever the booklet says at the
     * moment it is opened.
     */
    public function lendByLink(): void
    {
        $this->authorize('update', $this->booklet);

        $this->shareUrl = $this->urlForToken($this->booklet->mintLoan()->token);
    }

    /**
     * Take it back. The scores the booklet reaches were never minted onto
     * anybody — they are derived from this loan on every request — so this closes
     * the pages and the systems under them at once.
     */
    public function recallLoan(): void
    {
        $this->authorize('update', $this->booklet);

        $this->booklet->revokeLoans();

        $this->shareUrl = null;
    }

    /**
     * The one rule that cannot be stated as an attribute: the faces a booklet
     * may be set in are the ones the exporter can embed, and that list lives
     * with the rest of the font handling in BookletSettingFields.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'textFont' => ['required', 'string', Rule::in(BookletSettingFields::fontOptions())],
        ];
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', ['title' => $this->booklet->title]);
    }

    /**
     * The booklet as the plan it was made from: slots, their music, and what of
     * it was taken.
     *
     * This is the whole left-hand pane, and it is also the order the booklet is
     * printed in — walking it is what the sequences are written from, so the pane
     * and the pages can never say different things.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function outline(): array
    {
        return app(BookletOutline::class)->for(
            $this->booklet,
            $this->entries,
            $this->chosenScoreIds,
            $this->chosenFileIds,
        );
    }

    /**
     * The chosen entries, in order.
     *
     * @return Collection<int, BookletScore>
     */
    #[Computed]
    public function entries(): Collection
    {
        return app(BookletRenderPayload::class)->entriesOf($this->booklet);
    }

    /**
     * What the browser needs to draw the booklet.
     *
     * Built by BookletRenderPayload rather than here, because the musicians
     * reading the shared link draw the same pages from the same payload and the
     * two must not be able to disagree.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function renderPayload(): array
    {
        return app(BookletRenderPayload::class)->entries(
            $this->booklet,
            $this->entries,
            $this->entrySources,
            $this->headings,
        );
    }

    /**
     * What is printed above each entry, resolved from the plan rather than
     * stored. BookletRenderPayload owns the rules; the pane and the pages both
     * read them from there.
     *
     * @return array<int, array{slot: ?string, music: ?string, reference: ?string, variation: ?string}>
     */
    #[Computed]
    public function headings(): array
    {
        return app(BookletRenderPayload::class)->headingsFor($this->entries, Auth::user());
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
        return $this->entries->whereNotNull('score_id')->pluck('score_id')->all();
    }

    /**
     * The typed source of each score in the booklet, resolved once per render:
     * both the pages and the ticks in the list are drawn from it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function entrySources(): Collection
    {
        return app(BookletRenderPayload::class)->sourcesFor($this->entries, Auth::user());
    }

    /**
     * The uploaded files already in the booklet, for ticking a score that offers
     * more than one of them.
     *
     * Resolved rather than read off the rows: a row that names no file is the
     * score's default file, and it ticks that file's line.
     *
     * @return list<int>
     */
    #[Computed]
    public function chosenFileIds(): array
    {
        $sources = $this->entrySources;

        return $this->entries
            ->whereNotNull('score_id')
            ->map(function (BookletScore $entry) use ($sources): ?int {
                $source = $sources->get($entry->score_id);

                return $source === null ? null : app(BookletRenderPayload::class)->fileOf($entry, $source)['file_id'];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['title', 'pageSize', 'orientation', 'marginMm', 'lyricSizePt', 'staffHeightMm', 'textFont', 'headingScale', 'abcStaffSep'], true)) {
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
            'text_font' => $this->textFont,
            'heading_scale' => $this->headingScale,
            'abc_staff_sep' => $this->abcStaffSep,
        ]);

        unset($this->geometry);

        $this->forgetEntries();
    }

    /**
     * Add or remove one score, or one of the files it holds.
     *
     * Adding checks that the viewer may actually read it, so a score id typed
     * into a request cannot pull someone else's work into a booklet, and a file
     * id is honoured only where it is one of that score's own drawable files.
     * The assignment it was chosen from rides along, and the slot with it,
     * because that — not the score — is what names it on the page and what says
     * where it lands: under its own music, in its own slot, rather than at the
     * end. Nothing has to be moved into place afterwards, because the order is
     * read back off the plan.
     *
     * A score holding several files may be in the booklet several times over,
     * once per file, so it is the file that is toggled rather than the score.
     */
    public function toggleScore(int $scoreId, ?int $assignmentId = null, ?int $fileId = null): void
    {
        $this->authorize('update', $this->booklet);

        $source = app(MusicPlanScoreListService::class)->sourcesFor([$scoreId], Auth::user())->get($scoreId);

        if ($source === null) {
            // Unreadable now — a recalled loan, an unpublished score. It cannot
            // be added, but one already standing in the booklet must still be
            // removable.
            $stale = $this->booklet->entries()->where('score_id', $scoreId)->first();

            if ($stale instanceof BookletScore) {
                $this->removeEntry($stale->id);
            }

            return;
        }

        if ($fileId !== null && ! isset($source['files'][$fileId])) {
            return;
        }

        $existing = $this->booklet->entries()
            ->where('score_id', $scoreId)
            ->get()
            ->first(fn (BookletScore $entry): bool => app(BookletRenderPayload::class)->fileOf($entry, $source)['file_id'] === ($fileId ?? $source['file_id']));

        if ($existing instanceof BookletScore) {
            $this->removeEntry($existing->id);

            return;
        }

        $assignment = $this->assignmentInPlan($assignmentId);

        $order = $this->outlineIds();
        $at = app(BookletOutline::class)->appendIndex(
            $this->outline,
            $assignment?->music_plan_slot_plan_id,
            $assignment?->id,
        );

        $entry = $this->booklet->entries()->create([
            'score_id' => $scoreId,
            'score_file_id' => $fileId,
            'music_plan_slot_assignment_id' => $assignment?->id,
            'music_plan_slot_plan_id' => $assignment?->music_plan_slot_plan_id,
            'sequence' => (int) $this->booklet->entries()->max('sequence') + 1,
        ]);

        array_splice($order, $at, 0, [$entry->id]);

        $this->applyOrder($order);
    }

    /**
     * Add a paragraph of instructions — words the booklet says rather than sings.
     *
     * Words belong to the moment they introduce, so a paragraph is written into
     * the plan like everything else: at the head of a slot, at the head of one of
     * its musics, or straight after a row already standing there. Given none of
     * those, it opens the booklet.
     *
     * Words that open the booklet are almost always its name, so a paragraph
     * written as its very first row starts off holding the title as a heading —
     * unless the booklet opens with words already, in which case whatever they
     * are is the opening it has, and the new paragraph starts empty like any
     * other.
     */
    public function addText(?int $slotPlanId = null, ?int $assignmentId = null, ?int $afterEntryId = null): void
    {
        $this->authorize('update', $this->booklet);

        $assignment = $this->assignmentInPlan($assignmentId);
        $slotPlanId = $assignment?->music_plan_slot_plan_id ?? $this->slotInPlan($slotPlanId);

        $order = $this->outlineIds();
        $at = app(BookletOutline::class)->insertIndex(
            $this->outline,
            $slotPlanId,
            $assignment?->id,
            in_array($afterEntryId, $order, true) ? $afterEntryId : null,
        );

        $entry = $this->booklet->entries()->create([
            'text' => $this->opensTheBooklet($at, $slotPlanId, $assignment?->id, $order)
                ? '# '.Str::ucfirst($this->booklet->title)
                : '',
            'music_plan_slot_assignment_id' => $assignment?->id,
            'music_plan_slot_plan_id' => $slotPlanId,
            'sequence' => (int) $this->booklet->entries()->max('sequence') + 1,
        ]);

        array_splice($order, $at, 0, [$entry->id]);

        $this->openedTextId = $entry->id;

        $this->applyOrder($order);
    }

    /**
     * Whether a paragraph about to be written is the one that opens the booklet:
     * the very first row, belonging to no slot and no music, of a booklet whose
     * own opening words have not been written yet.
     *
     * Only the first row is asked about, and only whether it is the booklet
     * speaking for itself. Words at the head of the first slot are that slot's
     * — the booklet is still nameless above them — and words further down say
     * nothing about how it opens, wherever in the plan they stand.
     *
     * @param  list<int>  $order  the rows as they are printed, before this one
     */
    private function opensTheBooklet(int $at, ?int $slotPlanId, ?int $assignmentId, array $order): bool
    {
        if ($at !== 0 || $slotPlanId !== null || $assignmentId !== null) {
            return false;
        }

        $first = $order === [] ? null : $this->entries->firstWhere('id', $order[0]);

        if (! $first instanceof BookletScore) {
            return true;
        }

        return ! $first->isText()
            || $first->music_plan_slot_plan_id !== null
            || $first->music_plan_slot_assignment_id !== null;
    }

    /**
     * A row changed something it prints.
     *
     * A row keeps itself; what the pages look like is put together from the
     * whole booklet, which only this knows how to do.
     */
    #[On('booklet-entry-changed')]
    public function entryChanged(): void
    {
        $this->forgetEntries();
    }

    /**
     * Turn the slot's name on or off in the printout.
     *
     * The switch sits in the plan beside the name it governs, but the name is
     * spoken by one row — whichever opens the slot — so the choice is stored on
     * that row. The plan hands its id in; this only has to be sure it is one of
     * the booklet's own.
     */
    public function toggleSlotName(int $entryId): void
    {
        $this->toggleHeadingLine($entryId, 'show_slot');
    }

    /**
     * Turn a music's own name on or off, the same way — stored on the row that
     * opens the music, whether that row is a score or a paragraph.
     */
    public function toggleMusicName(int $entryId): void
    {
        $this->toggleHeadingLine($entryId, 'show_music_title');
    }

    /**
     * Turn off — or back on — the collections the music can be looked up in,
     * kept on the row that opens the music like the names above it.
     */
    public function toggleMusicCollections(int $entryId): void
    {
        $this->toggleHeadingLine($entryId, 'show_collections');
    }

    private function toggleHeadingLine(int $entryId, string $column): void
    {
        $this->authorize('update', $this->booklet);

        $entry = $this->booklet->entries()->find($entryId);

        if (! $entry instanceof BookletScore) {
            return;
        }

        $entry->update([$column => ! $entry->{$column}]);

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

        $this->forgetEntries();
    }

    /**
     * Move one row past the one beside it, inside the music — or the slot, or
     * the booklet itself — that it belongs to.
     */
    public function move(int $entryId, int $direction): void
    {
        $this->moveNode('entry', $entryId, $direction);
    }

    /**
     * Move a whole slot, and everything the booklet takes from it, past the slot
     * beside it.
     *
     * This is the one place the booklet is allowed to disagree with the plan
     * about order — the extra songs sung at the end of the plan, printed at the
     * front of the booklet — and it is the only ordering that crosses a slot.
     */
    public function moveSlot(int $slotPlanId, int $direction): void
    {
        $this->moveNode('slot', $slotPlanId, $direction);
    }

    /**
     * Move one music, and every score of it, past the music beside it — inside
     * its own slot and no further.
     */
    public function moveMusic(int $assignmentId, int $direction): void
    {
        $this->moveNode('music', $assignmentId, $direction);
    }

    /**
     * Nothing may leave the thing it belongs to, so a move is made on the tree
     * and not on the list: the outline swaps two of one container's children and
     * hands back the printed order that follows from it.
     */
    private function moveNode(string $kind, int $id, int $direction): void
    {
        $this->authorize('update', $this->booklet);

        $outline = app(BookletOutline::class);
        $moved = $outline->moved($this->outline, $kind, $id, $direction);

        if ($moved === null) {
            return;
        }

        $this->applyOrder($outline->flatten($moved));
    }

    /**
     * The printed order the tree now reads out.
     *
     * Every change is made against this rather than against the sequences, which
     * is what keeps the printed order and the plan the pane draws in step.
     *
     * @return list<int>
     */
    private function outlineIds(): array
    {
        return app(BookletOutline::class)->flatten($this->outline);
    }

    /**
     * Put the booklet back into the order the plan reads it in.
     *
     * A booklet made before the pane was the plan — or one whose plan has been
     * rearranged since — can hold an order no tree could produce, and the pages
     * are printed from the sequences rather than from the tree. So the two are
     * squared up when the editor is opened, and only if they differ.
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
     * Put the entries in the given order.
     *
     * Sequences are rewritten from scratch, so a list that had drifted out of
     * step — a deletion, an older booklet — comes back in order.
     *
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
        $entries = $this->booklet->entries()->get()->keyBy('id');

        foreach ($entryIds as $position => $id) {
            $entries[$id]?->update(['sequence' => $position]);
        }
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

        $clean = BookletSettingFields::sanitize(self::overrideFormat($entry), $override);

        $entry->update(['settings_override' => $clean === [] ? null : $clean]);

        $this->forgetEntries();
    }

    /**
     * Which set of knobs a row answers to.
     *
     * A score engraved from source answers to its format's; an uploaded one has
     * no format and answers to the single knob a picture has.
     */
    public static function overrideFormat(BookletScore $entry): ?string
    {
        if ($entry->isText()) {
            return null;
        }

        return $entry->score?->format?->value ?? 'file';
    }

    public function resetOverride(int $entryId): void
    {
        $this->saveOverride($entryId, []);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.booklet-editor');
    }

    private function urlForToken(?string $token): ?string
    {
        return $token === null ? null : route('booklet.loan', ['token' => $token]);
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
     * The slot, but only when it really belongs to this booklet's plan — for the
     * same reason the assignment is checked, and against the same plan.
     */
    private function slotInPlan(?int $slotPlanId): ?int
    {
        if ($slotPlanId === null || $this->booklet->music_plan_id === null) {
            return null;
        }

        return MusicPlanSlotPlan::query()
            ->where('id', $slotPlanId)
            ->where('music_plan_id', $this->booklet->music_plan_id)
            ->value('id');
    }

    /**
     * Throw away everything read off the booklet, so the next question about it
     * is asked of the database.
     */
    private function forget(): void
    {
        $this->booklet->unsetRelation('entries');
        unset($this->entries, $this->entrySources, $this->renderPayload, $this->chosenScoreIds, $this->chosenFileIds, $this->headings, $this->outline);
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
        $this->forget();

        $this->dispatch(
            'booklet-updated',
            payload: $this->renderPayload,
            geometry: $this->geometry,
        );
    }
}
