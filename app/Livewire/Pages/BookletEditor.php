<?php

namespace App\Livewire\Pages;

use App\Enums\BookletOrientation;
use App\Enums\BookletPageSize;
use App\Models\Booklet;
use App\Models\BookletScore;
use App\Models\MusicPlan;
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
 * The component owns the choosing: which scores are in, in what order, and what
 * had to be nudged to make each one sit well. It owns none of the drawing —
 * every page is engraved in the browser from the scores themselves, because that
 * is where the four renderers live and because nothing about a booklet is worth
 * storing as a picture.
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
     * The score whose settings the override panel is open on.
     */
    public ?int $editingScoreId = null;

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
     * The chosen scores, in order.
     *
     * @return Collection<int, BookletScore>
     */
    #[Computed]
    public function entries(): Collection
    {
        return $this->booklet->entries()->with('score')->get();
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

        $sources = app(MusicPlanScoreListService::class)
            ->sourcesFor($entries->pluck('score_id')->all(), Auth::user());

        return $entries
            ->map(function (BookletScore $entry) use ($sources): ?array {
                $source = $sources->get($entry->score_id);

                if ($source === null) {
                    return null;
                }

                return [
                    'id' => $entry->score_id,
                    'title' => $source['title'],
                    'format' => $source['format'],
                    'content' => $source['content'],
                    'settings' => $source['settings'],
                    'credit' => $source['credit'],
                    'override' => $entry->settings_override ?? [],
                    'startOnNewPage' => $entry->start_on_new_page,
                ];
            })
            ->filter()
            ->values()
            ->all();
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
        return $this->entries()->pluck('score_id')->all();
    }

    public function updated(string $property): void
    {
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
     * into a request cannot pull someone else's work into a booklet.
     */
    public function toggleScore(int $scoreId): void
    {
        $this->authorize('update', $this->booklet);

        $existing = $this->booklet->entries()->where('score_id', $scoreId)->first();

        if ($existing instanceof BookletScore) {
            $existing->delete();

            if ($this->editingScoreId === $scoreId) {
                $this->editingScoreId = null;
            }

            $this->forgetEntries();

            return;
        }

        $readable = app(MusicPlanScoreListService::class)->sourcesFor([$scoreId], Auth::user());

        if (! $readable->has($scoreId)) {
            return;
        }

        $this->booklet->entries()->create([
            'score_id' => $scoreId,
            'sequence' => (int) $this->booklet->entries()->max('sequence') + 1,
        ]);

        $this->forgetEntries();
    }

    public function move(int $scoreId, int $direction): void
    {
        $this->authorize('update', $this->booklet);

        $ordered = $this->booklet->entries()->get()->values()->all();
        $index = null;

        foreach ($ordered as $position => $entry) {
            if ($entry->score_id === $scoreId) {
                $index = $position;
                break;
            }
        }

        $target = $index === null ? null : $index + ($direction < 0 ? -1 : 1);

        if ($target === null || $target < 0 || $target >= count($ordered)) {
            return;
        }

        array_splice($ordered, $target, 0, array_splice($ordered, $index, 1));

        // Sequences are rewritten from scratch, so a list that had drifted out of
        // step — a deletion, an older booklet — comes back in order.
        foreach ($ordered as $position => $entry) {
            $entry->update(['sequence' => $position]);
        }

        $this->forgetEntries();
    }

    public function toggleStartOnNewPage(int $scoreId): void
    {
        $this->authorize('update', $this->booklet);

        $entry = $this->booklet->entries()->where('score_id', $scoreId)->first();

        if ($entry instanceof BookletScore) {
            $entry->update(['start_on_new_page' => ! $entry->start_on_new_page]);
            $this->forgetEntries();
        }
    }

    public function editSettings(?int $scoreId): void
    {
        $this->editingScoreId = $scoreId;
    }

    /**
     * Store one score's hand-made adjustments for this booklet.
     *
     * Sanitised against BookletSettingFields rather than trusted: the bucket is
     * arbitrary JSON from a browser, and it is replayed into a renderer.
     *
     * @param  array<string, mixed>  $override
     */
    public function saveOverride(int $scoreId, array $override): void
    {
        $this->authorize('update', $this->booklet);

        $entry = $this->booklet->entries()->with('score')->where('score_id', $scoreId)->first();

        if (! $entry instanceof BookletScore) {
            return;
        }

        $clean = BookletSettingFields::sanitize($entry->score?->format?->value, $override);

        $entry->update(['settings_override' => $clean === [] ? null : $clean]);

        $this->forgetEntries();
    }

    public function resetOverride(int $scoreId): void
    {
        $this->saveOverride($scoreId, []);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.booklet-editor');
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
        unset($this->entries, $this->renderPayload, $this->chosenScoreIds);

        $this->dispatch(
            'booklet-updated',
            payload: $this->renderPayload,
            geometry: $this->geometry,
        );
    }
}
