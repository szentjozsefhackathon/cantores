<?php

use App\Livewire\Booklet\EntryRow;
use App\Livewire\Pages\BookletEditor;
use App\Livewire\Pages\Booklets;
use App\Models\Booklet;
use App\Models\BookletScore;
use App\Models\Collection as CollectionModel;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\User;
use App\Support\BookletSettingFields;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

function bookletFor(User $user, ?MusicPlan $plan = null): Booklet
{
    return Booklet::factory()->create([
        'user_id' => $user->id,
        'music_plan_id' => $plan?->getKey(),
    ]);
}

it('creates a booklet from a music plan and names it after the celebration', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    post(route('booklets.store'), ['music_plan_id' => $plan->id])
        ->assertRedirect();

    $booklet = Booklet::query()->where('user_id', $user->id)->firstOrFail();

    expect($booklet->music_plan_id)->toBe($plan->id)
        ->and($booklet->page_size->value)->toBe('a5')
        ->and($booklet->entries()->count())->toBe(0);
});

it('refuses to start a booklet from someone elses private plan', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => true]);

    actingAs($stranger);

    post(route('booklets.store'), ['music_plan_id' => $plan->id])->assertForbidden();
});

it('adds a score and gives it the next place in the order', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $first = Score::factory()->abc()->create(['user_id' => $user->id]);
    $second = Score::factory()->abc()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $first->id)
        ->call('toggleScore', $second->id);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$first->id, $second->id]);
});

it('removes a score when it is toggled again', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $score->id)
        ->call('toggleScore', $score->id);

    expect($booklet->entries()->count())->toBe(0);
});

it('will not pull in a score the viewer cannot read', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $booklet = bookletFor($user);
    $theirs = Score::factory()->abc()->create(['user_id' => $stranger->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $theirs->id);

    expect($booklet->entries()->count())->toBe(0);
});

/**
 * @return array{0: \App\Models\Booklet, 1: \Illuminate\Support\Collection<int, BookletScore>}
 */
function bookletWithEntries(User $user, int $count): array
{
    $booklet = bookletFor($user);

    $entries = Score::factory()->abc()->count($count)->create(['user_id' => $user->id])
        ->values()
        ->map(fn (Score $score, int $index): BookletScore => BookletScore::factory()->create([
            'booklet_id' => $booklet->id,
            'score_id' => $score->id,
            'sequence' => $index,
        ]));

    return [$booklet, $entries];
}

/**
 * What the browser would be handed to draw the booklet as it now stands.
 *
 * Read afresh: a row changed by its own component is a change the page beside it
 * knows nothing about until it is asked again.
 *
 * @return list<array<string, mixed>>
 */
function payloadOf(Booklet $booklet): array
{
    return Livewire::test(BookletEditor::class, ['booklet' => $booklet])->get('renderPayload');
}

/**
 * Ask one row to do something to itself.
 *
 * A row of the booklet is a component of its own, so what it prints — whether it
 * starts a page, whether it names its music — is asked of the row rather than of
 * the booklet around it.
 */
function tellRow(BookletScore $entry, string $action): void
{
    Livewire::test(EntryRow::class, ['entry' => $entry])->call($action);
}

it('reorders scores and renumbers the whole list', function () {
    $user = User::factory()->create();
    [$booklet, $entries] = bookletWithEntries($user, 3);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('move', $entries[2]->id, -1);

    expect($booklet->entries()->orderBy('sequence')->pluck('id')->all())
        ->toBe([$entries[0]->id, $entries[2]->id, $entries[1]->id]);
});

it('leaves the order alone when a score is already at the end', function () {
    $user = User::factory()->create();
    [$booklet, $entries] = bookletWithEntries($user, 2);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('move', $entries[1]->id, 1);

    expect($booklet->entries()->orderBy('sequence')->pluck('id')->all())
        ->toBe([$entries[0]->id, $entries[1]->id]);
});

// Sequences drift apart as entries come and go; a move must close the gaps
// rather than move one entry into a number another entry already holds.
it('renumbers sequences that had drifted out of step', function () {
    $user = User::factory()->create();
    [$booklet, $entries] = bookletWithEntries($user, 3);

    $entries[1]->update(['sequence' => 5]);
    $entries[2]->update(['sequence' => 9]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('move', $entries[0]->id, 1);

    expect($booklet->entries()->orderBy('sequence')->pluck('sequence')->all())
        ->toBe([0, 1, 2])
        ->and($booklet->entries()->orderBy('sequence')->pluck('id')->all())
        ->toBe([$entries[1]->id, $entries[0]->id, $entries[2]->id]);
});

it('leaves the order alone when the entry belongs to another booklet', function () {
    $user = User::factory()->create();
    [$booklet, $entries] = bookletWithEntries($user, 3);
    $elsewhere = BookletScore::factory()->create(['booklet_id' => bookletFor($user)->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('move', $elsewhere->id, -1);

    expect($booklet->entries()->orderBy('sequence')->pluck('id')->all())
        ->toBe([$entries[0]->id, $entries[1]->id, $entries[2]->id]);
});

// A Blade directive inside a component tag — `@disabled(...)` — stops Blade from
// compiling that tag at all. The browser is then handed a literal <flux:button>
// it never closes, which both hides the control and swallows everything after it
// into a phantom element, so the next Livewire update has nothing to morph onto.
it('compiles every component tag on the page', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem', 'Uram, irgalmazz']);

    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        ->call('toggleScore', $scores[1]->id, $assignments[1]->id)
        ->call('addText')
        ->html();

    expect($html)->not->toContain('<flux:');
});

// The plan can run far longer than the pages, or the other way round; neither
// side may drag the other along when it is scrolled.
it('gives the plan and the pages a scroll box each', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);

    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => $booklet])->html();

    $panes = collect(['plan', 'pages'])->mapWithKeys(function (string $pane) use ($html) {
        preg_match('/<div\b[^>]*data-booklet-pane="'.$pane.'"[^>]*>/', $html, $matches);

        return [$pane => $matches[0] ?? ''];
    });

    expect($panes['plan'])->toContain('lg:overflow-y-auto')
        ->and($panes['pages'])->toContain('lg:overflow-y-auto');
});

it('divides the plan from the preview with a draggable handle', function () {
    $user = User::factory()->create();
    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => bookletFor($user)])->html();

    preg_match('/<div\b[^>]*data-booklet-handle[^>]*>/', $html, $matches);
    $handle = $matches[0] ?? '';

    expect($html)->toContain('booklet-split')
        ->and($handle)->toContain('role="separator"')
        ->and($handle)->toContain(__('Resize the preview'))
        ->and($handle)->toContain('startSplitDrag')
        ->and(strpos($html, 'data-booklet-handle'))
        ->toBeGreaterThan(strpos($html, 'data-booklet-pane="plan"'))
        ->and(strpos($html, 'data-booklet-handle'))
        ->toBeLessThan(strpos($html, 'data-booklet-pane="pages"'));
});

it('leaves the divider where it was dragged when the booklet is laid out again', function () {
    $user = User::factory()->create();
    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => bookletFor($user)])
        ->set('lyricSizePt', 11)
        ->html();

    preg_match('/<div\b[^>]*class="booklet-split[^>]*>/', $html, $row);
    preg_match('/<div\b[^>]*data-booklet-handle[^>]*>/', $html, $handle);

    // A morph strips every attribute the server did not send, and the split is
    // written into the row's style attribute from the browser alone.
    expect($row[0] ?? '')->toContain('wire:ignore.self')
        ->and($handle[0] ?? '')->toContain('wire:ignore.self');
});

it('keeps the export button spinning while the booklet is laid out again', function () {
    $user = User::factory()->create();
    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => bookletFor($user)])
        ->set('lyricSizePt', 11)
        ->html();

    preg_match('/<button\b[^>]*x-bind:data-loading[^>]*>/', $html, $button);

    // Neither the spinner nor the disabling is sent from the server, so a morph
    // arriving mid-layout must not be allowed to take them off.
    expect($button[0] ?? '')->toContain('wire:ignore.self');
});

it('leaves the export button alone while the rest of the booklet talks to the server', function () {
    $user = User::factory()->create();
    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => bookletFor($user)])->html();

    preg_match('/<button\b[^>]*x-bind:data-loading[^>]*>/', $html, $button);

    // Flux sets a button that can spin to spin whenever Livewire is busy, and one
    // that names no action of its own answers every action there is — which had
    // this one spinning while a score's toolbar was being opened. The export it
    // is named for never goes to the server, so nothing there can claim it.
    expect($button[0] ?? '')->toContain('wire:target="exportPdf"');
});

it('reads the booklet once to draw it, however many places ask what is in it', function () {
    $user = User::factory()->create();
    [$booklet, $entries] = bookletWithEntries($user, 5);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet]);

    $reads = 0;
    DB::listen(function (QueryExecuted $query) use (&$reads): void {
        if (str_contains($query->sql, 'booklet_scores')) {
            $reads++;
        }
    });

    $component->call('$refresh');

    // The plan, the payload the browser draws from, the headings and the ticks in
    // the list are four questions about one and the same booklet. They are asked
    // through a computed property so that it is fetched once — but the caching
    // hangs off reading it as a property, and asking it as a method quietly went
    // round the cache and put the whole booklet together again, every time.
    expect($reads)->toBe(1);
});

it('keeps the preview references in the booklet renderer component', function () {
    $user = User::factory()->create();
    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => bookletFor($user)])->html();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    foreach (['measure', 'pages'] as $reference) {
        $component = $xpath->query('//*[@x-ref="'.$reference.'"]/ancestor::*[@x-data][1]')->item(0);

        expect($component)->not->toBeNull()
            ->and($component->getAttribute('x-data'))->toStartWith('bookletEditor(');
    }
});

// The booklet's own geometry is read the way the score editor's toolbars are: a
// knob is its icon, its name is the tooltip, and the whole row fits above the pages
// instead of standing as a block of labelled fields as tall as the preview beside
// it.
it('names every booklet setting by its icon rather than a label', function () {
    $user = User::factory()->create();
    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => bookletFor($user)])->html();

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($document);
    $toolbar = $xpath->query('//*[@data-booklet-toolbar]')->item(0);

    $settings = [
        __('Title'), __('Page size'), __('Orientation'), __('Margin (mm)'),
        __('Lyric size (pt)'), __('Staff height (mm)'), __('Text font'),
        __('Heading size (×)'),
    ];

    expect($toolbar)->not->toBeNull();

    foreach ($settings as $setting) {
        expect($xpath->query('.//*[@aria-label="'.$setting.'"]', $toolbar)->length)
            ->toBeGreaterThan(0, $setting.' is not named for a screen reader');

        expect($xpath->query('.//*[@data-flux-tooltip-content][contains(., "'.$setting.'")]', $toolbar)->length)
            ->toBeGreaterThan(0, $setting.' has no tooltip');
    }

    expect($xpath->query('.//*[@data-flux-icon]', $toolbar)->length)->toBeGreaterThanOrEqual(count($settings));
});

// A booklet is typeset again on every knob, and often comes back looking much as
// it did — a margin a millimetre narrower moves almost nothing. So the work has
// to announce itself rather than be left for the reader to spot, and it has to do
// so from the moment the knob is touched: the trip to the server and the render
// debounce that follow it are a second in which the preview would otherwise sit
// there looking finished while showing the booklet as it was.
it('says the booklet is being laid out again from the moment a knob is touched', function () {
    $user = User::factory()->create();
    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => bookletFor($user)])->html();

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($document);
    $toolbar = $xpath->query('//*[@data-booklet-toolbar]')->item(0);

    expect($toolbar)->not->toBeNull()
        ->and($toolbar->getAttribute('x-on:input'))->toContain('markBusy');

    // The announcement rides on the events the controls themselves fire as they
    // bubble up to the bar, so they have to be plain form controls.
    foreach ([__('Page size'), __('Margin (mm)'), __('Lyric size (pt)')] as $knob) {
        expect($xpath->query('.//select[@aria-label="'.$knob.'"] | .//input[@aria-label="'.$knob.'"]', $toolbar)->length)
            ->toBeGreaterThan(0, $knob.' is not a control whose changes reach the bar');
    }

    // The title is the one field on the bar that leaves the pages as they were.
    $quiet = $xpath->query('.//*[@data-booklet-quiet]', $toolbar);

    expect($quiet->length)->toBe(1)
        ->and($xpath->query('.//*[@aria-label="'.__('Title').'"]', $quiet->item(0))->length)->toBe(1);

    // Said where the knobs are — by the one control that has to be out of use
    // while the pages are stale anyway, and without a label that changes width
    // under a bar this full.
    $download = $xpath->query('.//button[contains(., "'.__('Download PDF').'")]', $toolbar)->item(0);

    expect($download)->not->toBeNull()
        ->and($download->getAttribute('x-bind:disabled'))->toContain('busy')
        ->and($download->getAttribute('x-bind:data-loading'))->toContain('busy')
        ->and($xpath->query('.//*[@data-flux-loading-indicator]', $download)->length)
        ->toBe(1, 'the button has no spinner of its own to show');

    // ...and said over the pages themselves, which fade while they are stale.
    $pages = $xpath->query('//*[@x-ref="pages"]')->item(0);
    $preview = $xpath->query('//*[@data-booklet-pane="pages"]/ancestor::*[contains(@class, "relative")][1]')->item(0);
    $badge = $xpath->query('.//*[@role="status"][@x-show="busy"]', $preview)->item(0);

    expect($pages->getAttribute('x-bind:class'))->toContain('busy')
        ->and($badge)->not->toBeNull()
        ->and(trim($badge->textContent))->toContain(__('Laying out…'));
});

it('separates the row ordering controls from the icon-only score options', function () {
    $user = User::factory()->create();
    [$booklet, $entries] = bookletWithEntries($user, 1);
    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->assertSee(__('Start on a new page'))
        ->assertSee(__('Adjust this score'))
        ->assertSee(__('Remove'));

    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);
    $navButtons = $xpath->query('//*[@data-entry-nav]//button');
    $optionButtons = $xpath->query('//*[@data-entry-options]//button');

    expect($navButtons)->toHaveCount(4)
        ->and($optionButtons)->toHaveCount(2);

    // Where a row stands in the list is the list's business — moving it, taking
    // it out, and writing words under it all change the order — so those four are
    // asked of the booklet; what the row prints is the row's own.
    foreach ($navButtons as $button) {
        expect($button->getAttribute('wire:click'))->toMatch('/^\$parent\.(move|removeEntry|addText)\(/');
    }

    foreach ($optionButtons as $button) {
        expect($button->getAttribute('wire:click'))->not->toContain('$parent.');
    }

    foreach ([...$navButtons, ...$optionButtons] as $button) {
        expect(trim($button->textContent))->toBe('')
            ->and($button->getAttribute('aria-label'))->not->toBe('');
    }

    // A switch says which way it is set, and saying it wrong is worse than not
    // saying it at all — so it is asked what it says, flipped, and asked again.
    $pressed = function (string $html, string $action): string {
        preg_match('/<button\b[^>]*wire:click="'.$action.'"[^>]*>/', $html, $matches);

        preg_match('/aria-pressed="(true|false)"/', $matches[0] ?? '', $state);

        return $state[1] ?? '';
    };

    $row = Livewire::test(EntryRow::class, ['entry' => $entries[0]->fresh()]);
    $before = $pressed($row->html(), 'toggleStartOnNewPage');

    expect($before)->not->toBe('')
        ->and($pressed($row->call('toggleStartOnNewPage')->html(), 'toggleStartOnNewPage'))
        ->toBe($before === 'true' ? 'false' : 'true');
});

// The slot's name, the music's own name and the variation each get an eye
// switch set beside the name it governs — the first two in the plan, the last
// on the row — so what reaches the printed page is turned on and off where it
// is read rather than from a row of unlabelled icons.
it('puts an eye switch beside each heading name it governs', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    $entryId = $booklet->entries()->firstOrFail()->id;

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.Livewire::test(BookletEditor::class, ['booklet' => $booklet])->html());
    $xpath = new DOMXPath($document);

    // The switch's region, the click it fires, and the state it reports.
    $switchIn = function (string $region, string $click) use ($xpath): ?DOMElement {
        foreach ($xpath->query('//*[@'.$region.']//button') as $button) {
            if ($button->getAttribute('wire:click') === $click) {
                return $button;
            }
        }

        return null;
    };

    $slotSwitch = $switchIn('data-plan-slot', 'toggleSlotName('.$entryId.')');
    $musicSwitch = $switchIn('data-plan-music', 'toggleMusicName('.$entryId.')');
    $variationSwitch = $switchIn('data-entry-card', 'toggleShowVariation');

    expect($slotSwitch)->not->toBeNull()
        ->and($musicSwitch)->not->toBeNull()
        ->and($variationSwitch)->not->toBeNull();

    // The slot and the music start shown, the variation hidden — and each says so.
    expect($slotSwitch->getAttribute('aria-pressed'))->toBe('true')
        ->and($musicSwitch->getAttribute('aria-pressed'))->toBe('true')
        ->and($variationSwitch->getAttribute('aria-pressed'))->toBe('false');

    // Flipping each one through its own component moves what the page prints.
    Livewire::test(BookletEditor::class, ['booklet' => $booklet])->call('toggleSlotName', $entryId);
    tellRow($booklet->entries()->firstOrFail(), 'toggleShowVariation');

    $payload = payloadOf($booklet);

    expect($payload[0]['slot'])->toBeNull()
        ->and($payload[0]['variation'])->toBe('Áldjad, én lelkem – orgonakíséret');
});

// The whole point of a row being a component of its own: the booklet around it can
// be redrawn — a margin moved, a font changed, a score added — without every row of
// a long list being built again on the server and sent over the wire.
it('leaves the rows alone when the booklet around them is drawn again', function () {
    $user = User::factory()->create();
    [$booklet] = bookletWithEntries($user, 3);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet]);

    expect(substr_count($component->html(), 'data-entry-options'))->toBe(3);

    expect(substr_count($component->set('marginMm', 15)->html(), 'data-entry-options'))
        ->toBe(0, 'the rows were built and sent all over again');
});

// A row keeps itself, but the pages are drawn from the whole booklet, which only
// the editor around it can put together. So a row that changes what it prints says
// so, and the editor hands the browser a fresh picture.
it('tells the booklet when a row changes what it prints', function () {
    $user = User::factory()->create();
    [$booklet, $entries] = bookletWithEntries($user, 1);

    actingAs($user);

    Livewire::test(EntryRow::class, ['entry' => $entries[0]])
        ->call('toggleStartOnNewPage')
        ->assertDispatched('booklet-entry-changed');

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->dispatch('booklet-entry-changed')
        ->assertDispatched('booklet-updated');
});

it('will not let a stranger change a row of someone elses booklet', function () {
    $user = User::factory()->create();
    [, $entries] = bookletWithEntries($user, 1);

    actingAs(User::factory()->create());

    Livewire::test(EntryRow::class, ['entry' => $entries[0]])
        ->call('toggleStartOnNewPage')
        ->assertForbidden();

    expect($entries[0]->refresh()->start_on_new_page)->toBeFalse();
});

// A row in a booklet is a score of a music sung at a moment in the service, and it
// is read in that order: the slot names the moment, the music beside it, and the
// score — the one engraving out of several that was actually chosen — stands in a
// card of its own beneath, with its opening notes. Both names are links, since both
// are things the person assembling the booklet may want to open.
it('reads slot, then music, then the score in a card of its own', function () {
    \Illuminate\Support\Facades\Storage::fake();
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);
    \Illuminate\Support\Facades\Storage::put($scores[0]->incipit_path, 'image');

    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        ->html();

    $musicHref = route('music-view', $assignments[0]->music_id);
    $scoreHref = route('scores.edit', ['score' => $scores[0]->id]);

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($document);

    // The Lucide path is the icon's identity: file-music for the score.
    $scoreIcon = '//*[@data-entry-card]//*[local-name()="path"][@d="M14 2v5a1 1 0 0 0 1 1h5"]';

    // Each is named once and in one place: the slot and the music by the plan the
    // row stands in, the score by the row itself.
    expect($xpath->query('//a[@href="'.$musicHref.'"]'))->toHaveCount(1)
        ->and($xpath->query('//a[@href="'.$scoreHref.'"]'))->toHaveCount(1)
        ->and($xpath->query('//*[@data-entry-header]//a[@href="'.$musicHref.'"]'))->toHaveCount(0)
        ->and($xpath->query('//*[@data-entry-card]//a[@href="'.$scoreHref.'"]'))->toHaveCount(1)
        ->and($xpath->query($scoreIcon)->length)->toBeGreaterThan(0)
        ->and($xpath->query('//*[@data-entry-card]//*[@data-entry-incipit]//*[local-name()="img"][@src="'.$scores[0]->incipitUrl().'"]')->length)
        ->toBeGreaterThan(0);

    // The row stands inside the music it was chosen for, which stands inside the
    // slot: the slot is read before the music, and the music before the score.
    $slot = $xpath->query('//*[@data-plan-slot]')->item(0);
    $music = $xpath->query('//*[@data-plan-slot]//*[@data-plan-music]')->item(0);

    expect($music)->not->toBeNull()
        ->and($xpath->query('.//*[@data-entry-card]', $music)->length)->toBe(1)
        ->and($slot->textContent)->toContain('Kezdőének')
        ->and(strpos($slot->textContent, 'Kezdőének'))
        ->toBeLessThan(strpos($slot->textContent, 'Áldjad, én lelkem'));

    // The card is bordered, so a score reads as one thing among the several a
    // music may be sung from.
    expect($xpath->query('//*[@data-entry-card]')->item(0)->getAttribute('class'))->toContain('border');
});

// A score keeps both names where it has both: the engraving's own title, and the
// variation that tells it from the other arrangements of the same music.
it('names the score and its variation on the card', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'title' => 'Áldjad, én lelkem',
        'variation_name' => 'orgonakíséret',
    ]);

    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $score->id)
        ->html();

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($document);
    $card = $xpath->query('//*[@data-entry-card]')->item(0);

    expect($card->textContent)->toContain('Áldjad, én lelkem')
        ->and($card->textContent)->toContain('orgonakíséret');
});

it('names the music of a score chosen outside the plan', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create(['user_id' => $user->id, 'title' => 'Ó jöjj, ó jöjj Emmánuel']);
    $booklet = bookletFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id, 'music_id' => $music->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $score->id)
        ->assertSeeHtml('href="'.route('music-view', $music).'"')
        ->assertSee($music->title);
});

it('shows available incipits in the music slot selector', function () {
    \Illuminate\Support\Facades\Storage::fake();
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    [, , $scores] = slotWithMusics($plan, 'Entrance', ['With incipit', 'Without incipit']);
    \Illuminate\Support\Facades\Storage::put($scores[0]->incipit_path, 'image');
    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => bookletFor($user, $plan)])
        ->assertSeeHtml('src="'.$scores[0]->incipitUrl().'"')
        ->assertDontSeeHtml('src="'.$scores[1]->incipitUrl().'"')
        ->assertSee(__('View full image'))
        ->assertSee('Without incipit');
});

it('saves geometry changes as they are made', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->set('pageSize', 'a4')
        ->set('lyricSizePt', 12.5)
        ->assertHasNoErrors();

    $booklet->refresh();

    expect($booklet->page_size->value)->toBe('a4')
        ->and($booklet->lyric_size_pt)->toBe(12.5)
        ->and($booklet->contentMm()['width'])->toBe(210.0 - 24);
});

// Everything the booklet writes rather than engraves — the headings, the
// rubrics, the page numbers — is set in one face at one size, and both are the
// booklet's to choose.
it('saves the face and the heading size the booklet is set in', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->set('textFont', 'EB Garamond')
        ->set('headingScale', 0.8)
        ->assertHasNoErrors();

    $booklet->refresh();

    expect($booklet->text_font)->toBe('EB Garamond')
        ->and($booklet->heading_scale)->toBe(0.8)
        ->and($booklet->geometry())->toMatchArray([
            'textFont' => 'EB Garamond',
            'headingScale' => 0.8,
        ]);
});

// ABC reserves its staff separation above the first staff as well as between
// two of them, so on a small page it is both how tightly the music stacks and
// how far the heading stands off it — a booklet-wide call, not each score's.
it('saves how tightly ABC stacks throughout the booklet', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->set('abcStaffSep', 18)
        ->assertHasNoErrors();

    $booklet->refresh();

    expect($booklet->abc_staff_sep)->toBe(18.0)
        ->and($booklet->geometry())->toMatchArray(['abcStaffSep' => 18.0]);
});

// A print of an A5 booklet is what set these: 10.5pt lyrics over a 5 mm staff
// balance, and a heading at 0.9 stands over them without shouting.
it('starts a booklet at the numbers a printed A5 booklet wanted', function () {
    // Created past the factory, so the columns answer with what the schema says.
    $booklet = Booklet::query()->create([
        'user_id' => User::factory()->create()->id,
        'title' => 'Booklet',
    ])->fresh();

    expect($booklet->geometry())->toMatchArray([
        'lyricSizePt' => 10.5,
        'staffHeightMm' => 5.0,
        'headingScale' => 0.9,
        'textFont' => 'Inter',
        'abcStaffSep' => 25.0,
    ]);
});

// rsvg-convert has no network: a face that cannot be embedded is a face that is
// not printed, so it is not one a booklet may be set in.
it('refuses a text font the exporter cannot embed', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->set('textFont', 'Comic Sans MS')
        ->assertHasErrors('textFont');

    expect($booklet->fresh()->text_font)->toBe('Inter');
});

// The whole point of holding overrides on the pivot: a booklet adjusts how a
// score is printed here, and changes nothing anywhere else.
it('keeps an override to one booklet and never writes it back to the score', function () {
    $user = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $user->id, 'settings' => ['abc' => ['paper' => ['abcPageWidth' => 1700]]]]);
    $one = bookletFor($user);
    $two = bookletFor($user);

    $entry = BookletScore::factory()->create(['booklet_id' => $one->id, 'score_id' => $score->id]);
    BookletScore::factory()->create(['booklet_id' => $two->id, 'score_id' => $score->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $one])
        ->call('saveOverride', $entry->id, ['abcPageWidth' => 700]);

    // Whole numbers come back from JSON as ints, so compare by value.
    expect($one->entries()->first()->settings_override)->toEqual(['abcPageWidth' => 700])
        ->and($two->entries()->first()->settings_override)->toBeNull()
        ->and($score->fresh()->settings)->toBe(['abc' => ['paper' => ['abcPageWidth' => 1700]]]);
});

it('clamps an out-of-range override and drops keys the format does not have', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);
    $entry = BookletScore::factory()->create(['booklet_id' => $booklet->id, 'score_id' => $score->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('saveOverride', $entry->id, [
            'abcPageWidth' => 999999,
            'aretinoStaffWidth' => 120,
            'nonsense' => 'x',
        ]);

    expect($booklet->entries()->first()->settings_override)->toEqual(['abcPageWidth' => 8000]);
});

it('forgets an override when it is reset', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);
    $entry = BookletScore::factory()->create([
        'booklet_id' => $booklet->id,
        'score_id' => $score->id,
        'settings_override' => ['abcPageWidth' => 700],
    ]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('resetOverride', $entry->id);

    expect($booklet->entries()->first()->settings_override)->toBeNull();
});

/**
 * The two entries a settings test needs: one score in each of two formats, in the
 * order they were added.
 *
 * @return array{0: \App\Models\Booklet, 1: BookletScore, 2: BookletScore}
 */
function bookletWithAbcAndGabc(User $user): array
{
    $booklet = bookletFor($user);

    foreach ([Score::factory()->abc(), Score::factory()->gabc()] as $factory) {
        $score = $factory->create(['user_id' => $user->id]);
        BookletScore::factory()->create([
            'booklet_id' => $booklet->id,
            'score_id' => $score->id,
            'sequence' => $booklet->entries()->count(),
        ]);
    }

    [$abc, $gabc] = $booklet->entries()->orderBy('sequence')->get()->all();

    return [$booklet, $abc, $gabc];
}

// Settings belong to one score, so they are asked for where that score stands.
// A panel below a list of twenty says nothing about which of them it adjusts.
it('opens a score’s settings inside the row they belong to', function () {
    $user = User::factory()->create();
    [$booklet, $abc, $gabc] = bookletWithAbcAndGabc($user);

    actingAs($user);

    // The list hands each entry a row of its own, so a panel cannot open anywhere
    // but in the row that asked for it — and it opens under that row's controls.
    $list = Livewire::test(BookletEditor::class, ['booklet' => $booklet])->html();

    expect($list)->toContain('wire:key="entry-'.$abc->id.'"')
        ->and($list)->toContain('wire:key="entry-'.$gabc->id.'"');

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.Livewire::test(EntryRow::class, ['entry' => $abc])->call('adjust')->html());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//li//*[@data-entry-options]/following-sibling::*[@data-booklet-panel="'.$abc->id.'"]')->length)
        ->toBe(1);
});

// A score's panel is the score editor's toolbar for that format, so it is read the
// same way and by the same pictures: whoever set a staff size in the editor should
// find the same control here without stopping to read a label.
it('gives a score’s settings the score editor’s icons and tooltips', function () {
    $user = User::factory()->create();
    [$booklet, , $gabc] = bookletWithAbcAndGabc($user);

    actingAs($user);

    $html = Livewire::test(EntryRow::class, ['entry' => $gabc])
        ->call('adjust')
        ->html();

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($document);
    $panel = $xpath->query('//*[@data-booklet-panel]')->item(0);
    $fields = BookletSettingFields::panelFor('gabc');

    expect($panel)->not->toBeNull()
        ->and($fields)->not->toBeEmpty();

    foreach ($fields as $field) {
        expect($field['icon'] ?? $field['glyph'] ?? null)->not->toBeNull($field['key'].' is named by neither icon nor glyph')
            ->and($xpath->query('.//*[@aria-label="'.$field['label'].'"]', $panel)->length)
            ->toBeGreaterThan(0, $field['key'].' is not named for a screen reader')
            ->and($xpath->query('.//*[@data-flux-tooltip-content][contains(., "'.$field['label'].'")]', $panel)->length)
            ->toBeGreaterThan(0, $field['key'].' has no tooltip');
    }

    // One icon per knob, and one more on the button that puts them all back.
    expect($xpath->query('.//*[@data-flux-icon]', $panel))->toHaveCount(count($fields) + 1);

    // The staff size wears the editor's own icon, path for path.
    expect($xpath->query('.//*[local-name()="path"][@d="m15 16 3 3 3-3"]', $panel)->length)->toBeGreaterThan(0);
});

// A knob turns blue the moment it is moved, and the override behind it may still be
// waiting to be sent. The save that follows used to take the blue away again: the
// server renders a class of its own here, and a morph writes it over Alpine's.
it('keeps a moved knob blue when the override is saved', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);

    foreach ([Score::factory()->gabc(), Score::factory()->chordpro()] as $factory) {
        $score = $factory->create(['user_id' => $user->id]);
        BookletScore::factory()->create([
            'booklet_id' => $booklet->id,
            'score_id' => $score->id,
            'sequence' => $booklet->entries()->count(),
        ]);
    }

    actingAs($user);

    // Both kinds of marker: the icons most knobs wear, and the letter the one
    // named by a glyph wears instead.
    foreach ($booklet->entries()->orderBy('sequence')->get() as $entry) {
        $html = Livewire::test(EntryRow::class, ['entry' => $entry])
            ->call('adjust')
            ->html();

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($document);
        $panel = $xpath->query('//*[@data-booklet-panel]')->item(0);
        $markers = $xpath->query('.//*[@*[name()="x-bind:class"]]', $panel);

        expect($markers->length)->toBeGreaterThan(0);

        foreach ($markers as $marker) {
            expect($marker->hasAttribute('wire:ignore.self'))
                ->toBeTrue('a marker that a morph may repaint');
        }
    }
});

// The score's own button carries the same news as the knobs inside it: that this
// score has been adjusted. Answered from the browser for the same two reasons — an
// override may still be waiting to be sent, and it is the booklet rather than the
// row that saves it, so the row is never told.
it('marks an adjusted score from the browser', function () {
    $user = User::factory()->create();
    [, $entries] = bookletWithEntries($user, 1);

    actingAs($user);

    $html = Livewire::test(EntryRow::class, ['entry' => $entries[0]])->html();

    preg_match('/<button\b[^>]*aria-expanded[^>]*>/', $html, $button);

    expect($button[0] ?? '')->toContain('hasOverride('.$entries[0]->id.')')
        ->and($button[0] ?? '')->toContain('wire:ignore.self');
});

// The one control the score editor names with a letter rather than a picture keeps
// its letter here too.
it('keeps the H of German notation', function () {
    expect(collect(BookletSettingFields::panelFor('chordpro'))->firstWhere('key', 'chordproGermanNotation')['glyph'])
        ->toBe('H');
});

// Every control names the entry it adjusts. A panel that only knew "the open
// one" was a panel the browser could leave pointing at the previous score,
// which is how a GABC panel came to be writing ABC keys and showing blanks.
it('names the entry and the format in every control of a panel', function () {
    $user = User::factory()->create();
    [$booklet, , $gabc] = bookletWithAbcAndGabc($user);

    actingAs($user);

    $html = Livewire::test(EntryRow::class, ['entry' => $gabc])
        ->call('adjust')
        ->html();

    expect($html)->toContain("settingsOf({$gabc->id})['staffSize']")
        ->and($html)->toContain("setOverride({$gabc->id}, 'staffSize'")
        ->and($html)->toContain("isOverridden({$gabc->id}, 'staffSize')")
        ->and($html)->not->toContain('abcPageWidth');
});

it('writes a paragraph of instructions inside the row it belongs to', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('addText')
        ->call('toggleScore', $score->id);

    $text = $booklet->entries()->whereNull('score_id')->firstOrFail();

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.Livewire::test(EntryRow::class, ['entry' => $text])->call('write')->html());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//li//*[@data-entry-options]/following-sibling::*//textarea[@*[name()="wire:model.live.debounce.600ms" and .="text"]]')->length)
        ->toBe(1);
});

// Half a line height is where stacked stanzas start to collide, so a booklet
// cannot override its way below it — an older score stored as 0 comes back up.
it('holds the lyric line spacing at its floor', function () {
    expect(BookletSettingFields::sanitize('abc', ['abcLyricSkip' => 0]))
        ->toBe(['abcLyricSkip' => 0.5])
        ->and(BookletSettingFields::sanitize('abc', ['abcLyricSkip' => 1.4]))
        ->toBe(['abcLyricSkip' => 1.4]);
});

// The gap under the staff is the same kind of knob and gets the same floor:
// below half a line height the lyrics are inside the music.
it('holds the staff to lyrics gap at its floor', function () {
    expect(BookletSettingFields::sanitize('abc', ['abcLyricFirstSkip' => 0]))
        ->toBe(['abcLyricFirstSkip' => 0.5])
        ->and(BookletSettingFields::sanitize('abc', ['abcLyricFirstSkip' => 0.7]))
        ->toBe(['abcLyricFirstSkip' => 0.7])
        ->and(BookletSettingFields::sanitize('abc', ['abcVocalSpace' => 20]))
        ->toBe([]);
});

// The booklet's renderer lays a score out at the page's width and sizes it by
// its staff and lyric knobs; nothing in it ever reads a zoom. So the zoom is not
// offered, and a stale one arriving from an older client is dropped rather than
// stored as a setting that moves nothing. A picture's own size knob stays: it is
// the only thing an uploaded score has.
it('offers no zoom on a score, and keeps the one an uploaded picture has', function () {
    foreach (['gabc' => 'zoom', 'abc' => 'abcZoom', 'aretino' => 'aretinoZoom'] as $format => $key) {
        expect(collect(BookletSettingFields::panelFor($format))->pluck('key'))
            ->not->toContain($key)
            ->and(BookletSettingFields::sanitize($format, [$key => 150]))
            ->toBe([]);
    }

    expect(collect(BookletSettingFields::panelFor('file'))->pluck('key'))
        ->toContain('fileZoom')
        ->and(BookletSettingFields::sanitize('file', ['fileZoom' => 0.6]))
        ->toBe(['fileZoom' => 0.6]);
});

it('only accepts a font the exporter can embed', function () {
    expect(BookletSettingFields::sanitize('abc', ['abcLyricFont' => 'Lora']))
        ->toBe(['abcLyricFont' => "'Lora'"])
        ->and(BookletSettingFields::sanitize('abc', ['abcLyricFont' => 'Comic Sans MS']))
        ->toBe([]);
});

it('hands the browser everything it needs to draw a score', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id, 'title' => 'Kyrie']);
    BookletScore::factory()->create(['booklet_id' => $booklet->id, 'score_id' => $score->id]);

    actingAs($user);

    $payload = Livewire::test(BookletEditor::class, ['booklet' => $booklet])->get('renderPayload');

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['kind'])->toBe('score')
        ->and($payload[0]['format'])->toBe('abc')
        ->and($payload[0]['content'])->toContain('X:1')
        ->and($payload[0])->not->toHaveKey('credit');
});

/**
 * A slot occurrence with the given musics, and one abc score for each.
 *
 * @param  list<string>  $musicTitles
 * @return array{0: \App\Models\MusicPlanSlotPlan, 1: \Illuminate\Support\Collection<int, MusicPlanSlotAssignment>, 2: \Illuminate\Support\Collection<int, Score>}
 */
function slotWithMusics(MusicPlan $plan, string $slotName, array $musicTitles): array
{
    $slot = MusicPlanSlot::factory()->create(['name' => $slotName]);
    $slotPlan = MusicPlanSlotPlan::factory()->create([
        'music_plan_id' => $plan->id,
        'music_plan_slot_id' => $slot->id,
        // The plan is sung in the order its slots were asked for here: the
        // booklet reads the plan's order, so leaving it to the factory would
        // leave the booklet's own order to chance.
        'sequence' => MusicPlanSlotPlan::where('music_plan_id', $plan->id)->count(),
    ]);

    $assignments = collect();
    $scores = collect();

    foreach (array_values($musicTitles) as $index => $title) {
        $music = Music::factory()->create(['user_id' => $plan->user_id, 'title' => $title]);
        $assignments->push(MusicPlanSlotAssignment::factory()->create([
            'music_plan_slot_plan_id' => $slotPlan->id,
            'music_id' => $music->id,
            'music_sequence' => $index,
        ]));
        $scores->push(Score::factory()->abc()->create([
            'user_id' => $plan->user_id,
            'music_id' => $music->id,
            'title' => $title,
            'variation_name' => $title.' – orgonakíséret',
        ]));
    }

    return [$slotPlan, $assignments, $scores];
}

/**
 * The pane's slots, top to bottom.
 *
 * @return list<\DOMElement>
 */
function planSlotElements(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

    $slots = [];

    foreach ((new DOMXPath($document))->query('//*[@data-plan-slot]') as $element) {
        $slots[] = $element;
    }

    return $slots;
}

it('puts the music title on the slots own line where the slot holds one music', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    $payload = payloadOf($booklet);

    // The moment and the music on one line, and nothing else said: the variation
    // is still the row's own choice.
    expect($payload[0]['slot'])->toBe('Kezdőének – Áldjad, én lelkem')
        ->and($payload[0]['music'])->toBeNull()
        ->and($payload[0]['variation'])->toBeNull();
});

it('keeps the music title off the page for a row told to', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    // A slot whose name already says the music: the switch — beside the music's
    // name in the plan — is how that name is stopped from being printed twice.
    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleMusicName', $booklet->entries()->firstOrFail()->id);

    $payload = payloadOf($booklet);

    expect($payload[0]['slot'])->toBe('Kezdőének')
        ->and($payload[0]['music'])->toBeNull();
});

it('still names the music beside the slot when the slot holds two engravings of it', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    $organ = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'music_id' => $scores[0]->music_id,
        'title' => 'Áldjad, én lelkem',
    ]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        ->call('toggleScore', $organ->id, $assignments[0]->id);

    $payload = payloadOf($booklet);

    // Two engravings, one music: there is still nothing to tell apart, so the
    // slot's line carries the name — once, over the first of them.
    expect($payload[0]['slot'])->toBe('Kezdőének – Áldjad, én lelkem')
        ->and($payload[1]['slot'])->toBeNull()
        ->and($payload[1]['music'])->toBeNull();
});

it('keeps every music of a shared slot on a line of its own', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Áldozás', ['Ének egy', 'Ének kettő']);

    // A second engraving of the first music, so the music's name has two rows to
    // be said over and must choose the first.
    $organ = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'music_id' => $scores[0]->music_id,
        'title' => 'Ének egy',
    ]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        ->call('toggleScore', $organ->id, $assignments[0]->id)
        ->call('toggleScore', $scores[1]->id, $assignments[1]->id);

    $payload = payloadOf($booklet);

    // Two musics under one slot are a list: the first is not promoted into the
    // slot's line, or the second would read as something lesser.
    expect($payload[0]['slot'])->toBe('Áldozás')
        ->and($payload[0]['music'])->toBe('Ének egy')
        ->and($payload[1]['slot'])->toBeNull()
        ->and($payload[1]['music'])->toBeNull()
        ->and($payload[2]['slot'])->toBeNull()
        ->and($payload[2]['music'])->toBe('Ének kettő');
});

it('prints the variation name only for the score that asked for it', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    tellRow($booklet->entries()->firstOrFail(), 'toggleShowVariation');

    expect(payloadOf($booklet)[0]['variation'])
        ->toBe('Áldjad, én lelkem – orgonakíséret');
});

it('keeps the slot name off the page for a row told to', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    // Printed unless the row says otherwise, the same way the music's name is.
    expect(payloadOf($booklet)[0]['slot'])->toBe('Kezdőének – Áldjad, én lelkem');

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleSlotName', $booklet->entries()->firstOrFail()->id);

    $payload = payloadOf($booklet);

    // The slot's name is gone. The music's name, which had been folded into the
    // slot's line, stands on its own now rather than being lost with it.
    expect($payload[0]['slot'])->toBeNull()
        ->and($payload[0]['music'])->toBe('Áldjad, én lelkem');
});

// A heading is read by whoever holds the booklet: it must come from that
// booklet's own service, never from someone else's.
it('ignores an assignment that is not in the booklets plan', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $other = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($other, 'Felajánlás', ['Idegen ének']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    expect($booklet->entries()->firstOrFail()->music_plan_slot_assignment_id)->toBeNull();
});

it('adds a paragraph of instructions and keeps its Markdown', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])->call('addText');

    $entry = $booklet->entries()->firstOrFail();

    // The paragraph is written in the row it belongs to, and saved as it is typed.
    Livewire::test(EntryRow::class, ['entry' => $entry])
        ->set('text', "**Álljunk fel.**\n\nA kántor énekli a verseket.");

    expect($entry->refresh()->isText())->toBeTrue()
        ->and($entry->text)->toContain('Álljunk fel')
        ->and(payloadOf($booklet)[0])
        ->toMatchArray(['kind' => 'text', 'id' => $entry->id]);
});

// The words a booklet opens with are almost always its name, so the paragraph
// written as its first row starts off holding the title as a heading — named
// the way a name is written, whatever case the plan handed the booklet.
it('opens the booklet with its title when the first words are written there', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $booklet->update(['title' => 'évközi 12. vasárnap']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])->call('addText');

    $entry = $booklet->entries()->firstOrFail();

    expect($entry->isText())->toBeTrue()
        ->and($entry->text)->toBe('# Évközi 12. vasárnap');
});

// What counts is how the booklet opens, not what it says elsewhere: paragraphs
// standing at the back of it, outside the plan, are not its name.
it('opens the booklet with its title though words stand at the back of it', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    $booklet->update(['title' => 'Advent vasárnapja']);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy']);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    // Words after the music, belonging to no slot — the shape a booklet takes
    // once something has been written under its last score.
    $trailing = $booklet->entries()->create(['text' => 'Vége', 'sequence' => 99]);

    $component->call('addText');

    $order = $booklet->entries()->orderBy('sequence')->get();

    expect($order->first()->text)->toBe('# Advent vasárnapja')
        ->and($order->last()->id)->toBe($trailing->id);
});

// Words at the head of the first slot are that slot's, and the booklet is still
// nameless above them — so it is named when something is written over them.
it('opens the booklet with its title above words that belong to the first slot', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    $booklet->update(['title' => 'Advent vasárnapja']);
    [$slotPlan, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy']);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        ->call('addText', $slotPlan->id);

    $component->call('addText');

    $order = $booklet->entries()->orderBy('sequence')->get();

    expect($order->first()->text)->toBe('# Advent vasárnapja')
        ->and($order->first()->music_plan_slot_plan_id)->toBeNull();
});

// Only the opening words, though: once the booklet says something of its own,
// the next paragraph is the writer's to fill.
it('leaves later words at the top of the booklet empty', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $booklet->update(['title' => 'Advent vasárnapja']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('addText')
        ->call('addText');

    $entries = $booklet->entries()->orderBy('sequence')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('text')->sort()->values()->all())
        ->toBe(['', '# Advent vasárnapja']);
});

// And only the first row: words written under a score that opens the booklet
// stand after it, so they are not the booklet's name.
it('leaves words written under the opening score empty', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    $booklet->update(['title' => 'Advent vasárnapja']);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy']);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    $score = $booklet->entries()->firstOrFail();

    $component->call('addText', null, null, $score->id);

    $written = $booklet->entries()->whereNull('score_id')->firstOrFail();

    expect($written->text)->toBe('');
});

// A paragraph is added in order to be written, so it opens with the cursor's place
// ready. A row hears nothing after it is first drawn, so being new is something it
// has to be told as it is drawn.
it('opens a new paragraph ready to be written in', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);

    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('addText')
        ->html();

    expect($html)->toContain('wire:model.live.debounce.600ms="text"');
});

it('sets the music name over the words written under it', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Áldozás', ['Ének egy', 'Ének kettő']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        // Written under the second music, so it stands between the two scores.
        ->call('addText', null, $assignments[1]->id)
        ->call('toggleScore', $scores[1]->id, $assignments[1]->id);

    $payload = payloadOf($booklet);

    // The words introduce the second music, so its name is set over them and the
    // score that follows does not say it again.
    expect($payload[1]['kind'])->toBe('text')
        ->and($payload[1]['slot'])->toBeNull()
        ->and($payload[1]['music'])->toBe('Ének kettő')
        ->and($payload[2]['slot'])->toBeNull()
        ->and($payload[2]['music'])->toBeNull();
});

it('lets the words carrying a music name keep it off the page', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [$slotPlan, $assignments, $scores] = slotWithMusics($plan, 'Áldozás', ['Ének egy', 'Ének kettő']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        ->call('addText', null, $assignments[1]->id)
        ->call('toggleScore', $scores[1]->id, $assignments[1]->id)
        ->call('addText', $slotPlan->id);

    $entries = $booklet->entries()->orderBy('sequence')->get();

    // The switch stands beside the music in the plan and points at the row that
    // speaks its name — here the words at the head of the music, not the rubric
    // that merely opens the slot.
    $plan = Livewire::test(BookletEditor::class, ['booklet' => $booklet])->html();

    expect($plan)->toContain('wire:click="toggleMusicName('.$entries[2]->id.')"')
        ->and($plan)->not->toContain('wire:click="toggleMusicName('.$entries[0]->id.')"');

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleMusicName', $entries[2]->id);

    $payload = payloadOf($booklet);

    // Told to keep quiet, the words say nothing — and do not hand the naming on
    // to the score beneath them either, the music having been introduced.
    expect($payload[2]['kind'])->toBe('text')
        ->and($payload[2]['music'])->toBeNull()
        ->and($payload[3]['music'])->toBeNull();
});

it('does not repeat a slot heading after a paragraph of instructions', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    // A second engraving of the same music, under the same slot: its heading is
    // the one that must not come round again.
    $organ = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'music_id' => $scores[0]->music_id,
        'title' => 'Áldjad, én lelkem',
    ]);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    // Written under the first engraving, so it stands between the two.
    $payload = $component
        ->call('addText', null, $assignments[0]->id, $booklet->entries()->firstOrFail()->id)
        ->call('toggleScore', $organ->id, $assignments[0]->id)
        ->get('renderPayload');

    expect($payload[0]['slot'])->toBe('Kezdőének – Áldjad, én lelkem')
        ->and($payload[1]['kind'])->toBe('text')
        ->and($payload[1]['slot'])->toBeNull()
        ->and($payload[1]['music'])->toBeNull()
        ->and($payload[2]['slot'])->toBeNull()
        ->and($payload[2]['music'])->toBeNull();
});

it('does not put a score panel on a text entry', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])->call('addText');

    $entry = $booklet->entries()->firstOrFail();

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('saveOverride', $entry->id, ['abcPageWidth' => 700]);

    expect($entry->fresh()->settings_override)->toBeNull();
});

it('refuses to open or change someone elses booklet', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $booklet = bookletFor($owner);

    actingAs($stranger);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->assertForbidden();
});

// A booklet is the scores of one service, so the list starts one by choosing
// that service rather than by making an empty booklet and wondering.
it('offers my own plans and starts a booklet from the chosen one', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $mine->id]);
    $notMine = MusicPlan::factory()->create(['user_id' => $theirs->id, 'is_private' => true]);

    actingAs($mine);

    $component = Livewire::test(Booklets::class);

    expect($component->get('selectablePlans')->pluck('id')->all())
        ->toBe([$plan->id]);

    $component->call('createFromPlan', $plan->id)
        ->assertRedirect(route('booklets.edit', ['booklet' => Booklet::query()->firstOrFail()->id]));

    expect(Booklet::query()->firstOrFail()->music_plan_id)->toBe($plan->id);

    $component->call('createFromPlan', $notMine->id)->assertForbidden();
});

it('lists only my own booklets', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    Booklet::factory()->create(['user_id' => $mine->id, 'title' => 'Adventi füzet']);
    Booklet::factory()->create(['user_id' => $theirs->id, 'title' => 'Karácsonyi füzet']);

    actingAs($mine);

    Livewire::test(Booklets::class)
        ->assertSee('Adventi füzet')
        ->assertDontSee('Karácsonyi füzet');
});

it('adds a score to the slot it was chosen from rather than to the end', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $opening, $openingScores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy', 'Ének kettő']);
    [, $communion, $communionScores] = slotWithMusics($plan, 'Áldozás', ['Ének három']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $openingScores[0]->id, $opening[0]->id)
        ->call('toggleScore', $communionScores[0]->id, $communion[0]->id)
        ->call('toggleScore', $openingScores[1]->id, $opening[1]->id);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$openingScores[0]->id, $openingScores[1]->id, $communionScores[0]->id]);
});

it('stops at the next slot and leaves the words already written where they stand', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $opening, $openingScores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy', 'Ének kettő']);
    [, $communion, $communionScores] = slotWithMusics($plan, 'Áldozás', ['Ének három']);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $openingScores[0]->id, $opening[0]->id);

    $component
        ->call('addText', null, $opening[0]->id, $booklet->entries()->firstOrFail()->id)
        ->call('toggleScore', $communionScores[0]->id, $communion[0]->id)
        ->call('toggleScore', $openingScores[1]->id, $opening[1]->id);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$openingScores[0]->id, null, $openingScores[1]->id, $communionScores[0]->id]);
});

it('puts a score into a slot the booklet has taken nothing from yet', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [$openingSlot, $opening, $openingScores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy']);
    [$communionSlot, $communion, $communionScores] = slotWithMusics($plan, 'Áldozás', ['Ének kettő']);

    $openingSlot->update(['sequence' => 0]);
    $communionSlot->update(['sequence' => 1]);

    actingAs($user);

    // Chosen back to front, and printed the way the service is sung: an empty
    // slot keeps the place the plan gives it, so what is put into it is put
    // there too, rather than after everything already chosen.
    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $communionScores[0]->id, $communion[0]->id)
        ->call('toggleScore', $openingScores[0]->id, $opening[0]->id);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$openingScores[0]->id, $communionScores[0]->id]);
});

it('leaves a slot where it stands when the last score is taken out of it', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [$first, $opening, $openingScores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy']);
    [$second, $offertory, $offertoryScores] = slotWithMusics($plan, 'Felajánlás', ['Ének kettő']);
    [$third, $communion, $communionScores] = slotWithMusics($plan, 'Áldozás', ['Ének három']);

    $first->update(['sequence' => 0]);
    $second->update(['sequence' => 1]);
    $third->update(['sequence' => 2]);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $openingScores[0]->id, $opening[0]->id)
        ->call('toggleScore', $offertoryScores[0]->id, $offertory[0]->id)
        ->call('toggleScore', $communionScores[0]->id, $communion[0]->id);

    // Emptying the middle slot says nothing about where it belongs, and the
    // pane still reads as the service: the slot is drawn between its
    // neighbours, waiting to be chosen from again.
    $component->call('toggleScore', $offertoryScores[0]->id, $offertory[0]->id);

    $slots = [];

    foreach (planSlotElements($component->html()) as $element) {
        $slots[] = $element->getAttribute('data-plan-slot');
    }

    expect($slots)->toBe([(string) $first->id, (string) $second->id, (string) $third->id]);

    // And putting it back is putting it back — not adding it to the end.
    $component->call('toggleScore', $offertoryScores[0]->id, $offertory[0]->id);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$openingScores[0]->id, $offertoryScores[0]->id, $communionScores[0]->id]);
});

it('lists each file of an uploaded score in the plan, and adds the one clicked', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['A boldog férfiú']);

    $uploaded = Score::factory()->linksOnly()->create([
        'user_id' => $user->id,
        'music_id' => $scores[0]->music_id,
        'title' => 'A boldog férfiú',
    ]);
    ScoreFile::factory()->banded(1)->create(['score_id' => $uploaded->id, 'label' => 'Vetítés']);
    $parts = ScoreFile::factory()->banded(4)->create(['score_id' => $uploaded->id, 'label' => 'SA kísérettel']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->assertSee('Vetítés')
        ->assertSee('SA kísérettel')
        ->call('toggleScore', $uploaded->id, $assignments[0]->id, $parts->id);

    $entry = $booklet->entries()->firstOrFail();

    expect($entry->score_file_id)->toBe($parts->id)
        ->and($entry->music_plan_slot_assignment_id)->toBe($assignments[0]->id);
});

/*
|--------------------------------------------------------------------------
| The pane is the plan
|--------------------------------------------------------------------------
|
| One list rather than two: the plan, with what the booklet took from it standing
| in place. Which brings the order under the plan's own shape — a slot may be
| moved against the plan, a music only inside its slot, a score only inside its
| music — and gives a paragraph of instructions somewhere to belong.
*/

it('shows the plan with the booklet standing inside it', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [$opening, $openingAssignments, $openingScores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy']);
    [$communion] = slotWithMusics($plan, 'Áldozás', ['Ének három']);

    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $openingScores[0]->id, $openingAssignments[0]->id)
        ->html();

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($document);

    $chosen = $xpath->query('//*[@data-plan-slot="'.$opening->id.'"]')->item(0);
    $untouched = $xpath->query('//*[@data-plan-slot="'.$communion->id.'"]')->item(0);

    // Both slots are on the pane, and the one the booklet takes from is marked so
    // that it can be picked out without reading a word of it.
    expect($chosen->getAttribute('class'))->toContain('border-green-500')
        ->and($untouched->getAttribute('class'))->not->toContain('border-green-500')
        ->and($xpath->query('.//*[@data-entry-card]', $chosen)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-entry-card]', $untouched)->length)->toBe(0);

    // And the score, being in the booklet, is no longer offered — while the
    // slot nothing was taken from still offers everything it has.
    expect(substr_count($html, 'toggleScore('.$openingScores[0]->id.', '.$openingAssignments[0]->id.')'))->toBe(0)
        ->and($untouched->textContent)->toContain('Ének három');
});

it('pulls a whole slot in front of another without touching the plan', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [$openingSlot, $opening, $openingScores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy']);
    [$extraSlot, $extra, $extraScores] = slotWithMusics($plan, 'Ráadás', ['Ének kettő', 'Ének három']);

    $openingSlot->update(['sequence' => 0]);
    $extraSlot->update(['sequence' => 1]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $openingScores[0]->id, $opening[0]->id)
        ->call('toggleScore', $extraScores[0]->id, $extra[0]->id)
        ->call('toggleScore', $extraScores[1]->id, $extra[1]->id)
        ->call('moveSlot', $extraSlot->id, -1);

    // Everything the slot holds moves with it, and the plan itself is left as it
    // was: the extra songs are printed first and still planned last.
    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$extraScores[0]->id, $extraScores[1]->id, $openingScores[0]->id])
        ->and($openingSlot->refresh()->sequence)->toBe(0)
        ->and($extraSlot->refresh()->sequence)->toBe(1);
});

it('will not move a music out of its slot', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $opening, $openingScores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy', 'Ének kettő']);
    [, $communion, $communionScores] = slotWithMusics($plan, 'Áldozás', ['Ének három']);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $openingScores[0]->id, $opening[0]->id)
        ->call('toggleScore', $openingScores[1]->id, $opening[1]->id)
        ->call('toggleScore', $communionScores[0]->id, $communion[0]->id);

    // The second music of the opening is the last thing in its slot: there is
    // nowhere further down for it to go, the communion being another slot's.
    $component->call('moveMusic', $opening[1]->id, 1);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$openingScores[0]->id, $openingScores[1]->id, $communionScores[0]->id]);

    // Upwards, inside its own slot, it moves.
    $component->call('moveMusic', $opening[1]->id, -1);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$openingScores[1]->id, $openingScores[0]->id, $communionScores[0]->id]);
});

it('will not move a score out of the music it was chosen for', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy', 'Ének kettő']);

    // A second engraving of the first music: the two of them are what a score may
    // be reordered against.
    $organ = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'music_id' => $scores[0]->music_id,
        'title' => 'Ének egy orgonára',
    ]);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        ->call('toggleScore', $organ->id, $assignments[0]->id)
        ->call('toggleScore', $scores[1]->id, $assignments[1]->id);

    $second = $booklet->entries()->orderBy('sequence')->get()[1];

    // Down would take it into the next music, so it stays where it is.
    $component->call('move', $second->id, 1);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$scores[0]->id, $organ->id, $scores[1]->id]);

    $component->call('move', $second->id, -1);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$organ->id, $scores[0]->id, $scores[1]->id]);
});

it('writes a paragraph into the plan where it was asked for', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [$slotPlan, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy']);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    $score = $booklet->entries()->firstOrFail();

    $component->call('addText', $slotPlan->id, null, null)
        ->call('addText', null, $assignments[0]->id, $score->id)
        ->call('addText');

    $entries = $booklet->entries()->orderBy('sequence')->get();

    // The booklet's own opening words, the slot's, the score, then the words
    // written under the score.
    expect($entries->pluck('score_id')->all())->toBe([null, null, $scores[0]->id, null])
        ->and($entries[0]->music_plan_slot_plan_id)->toBeNull()
        ->and($entries[1]->music_plan_slot_plan_id)->toBe($slotPlan->id)
        ->and($entries[1]->music_plan_slot_assignment_id)->toBeNull()
        ->and($entries[3]->music_plan_slot_assignment_id)->toBe($assignments[0]->id);
});

// Words written at the head of a slot are the first thing that slot says, so
// they must be drawn there — above the music nothing has been chosen from,
// which waits underneath and prints nothing.
it('shows words written at the head of a slot above the music not chosen from', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [$slotPlan] = slotWithMusics($plan, 'Kezdőének', ['Ének egy', 'Ének kettő']);

    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('addText', $slotPlan->id)
        ->html();

    $slot = planSlotElements($html)[0];
    $document = new DOMXPath($slot->ownerDocument);

    $text = $document->query('.//*[@data-entry="text"]', $slot)->item(0);
    $musics = $document->query('.//*[@data-plan-music]', $slot);

    expect($text)->not->toBeNull()
        ->and($musics->length)->toBe(2);

    foreach ($musics as $music) {
        expect($text->compareDocumentPosition($music) & DOMNode::DOCUMENT_POSITION_FOLLOWING)
            ->not->toBe(0);
    }
});

// The same at the top of the booklet: the opening words come before the slots,
// including the ones the booklet has taken nothing from yet.
it('shows words written at the top of the booklet above the slots not chosen from', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    slotWithMusics($plan, 'Kezdőének', ['Ének egy']);
    slotWithMusics($plan, 'Áldozás', ['Ének kettő']);

    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('addText')
        ->html();

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($document);

    $text = $xpath->query('//*[@data-entry="text"]')->item(0);
    $slots = $xpath->query('//*[@data-plan-slot]');

    expect($text)->not->toBeNull()
        ->and($slots->length)->toBe(2);

    foreach ($slots as $slot) {
        expect($text->compareDocumentPosition($slot) & DOMNode::DOCUMENT_POSITION_FOLLOWING)
            ->not->toBe(0);
    }
});

it('lets the words at the head of a slot carry its name', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [$slotPlan, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy']);

    actingAs($user);

    $payload = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        ->call('addText', $slotPlan->id)
        ->get('renderPayload');

    // The heading stands over the words that introduce the moment, and the score
    // under them does not announce it a second time — though the music, which
    // the words said nothing of, is still named over it.
    expect($payload[0]['kind'])->toBe('text')
        ->and($payload[0]['slot'])->toBe('Kezdőének')
        ->and($payload[0]['music'])->toBeNull()
        ->and($payload[1]['slot'])->toBeNull()
        ->and($payload[1]['music'])->toBe('Ének egy');
});

it('refuses a slot from somebody elses plan', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $theirs = MusicPlan::factory()->create(['user_id' => User::factory()->create()->id]);
    [$stranger] = slotWithMusics($theirs, 'Kezdőének', ['Ének egy']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => bookletFor($user, $plan)])
        ->call('addText', $stranger->id);

    expect(BookletScore::query()->firstOrFail()->music_plan_slot_plan_id)->toBeNull();
});

it('straightens a booklet whose rows no longer follow the plan', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [$openingSlot, $opening, $openingScores] = slotWithMusics($plan, 'Kezdőének', ['Ének egy', 'Ének kettő']);
    [$communionSlot, $communion, $communionScores] = slotWithMusics($plan, 'Áldozás', ['Ének három']);

    // Written by hand as an older booklet could hold them: the communion sits
    // between the two opening songs, which is an order no plan can be read as.
    foreach ([
        [$openingScores[0], $opening[0], $openingSlot, 0],
        [$communionScores[0], $communion[0], $communionSlot, 1],
        [$openingScores[1], $opening[1], $openingSlot, 2],
    ] as [$score, $assignment, $slotPlan, $sequence]) {
        $booklet->entries()->create([
            'score_id' => $score->id,
            'music_plan_slot_assignment_id' => $assignment->id,
            'music_plan_slot_plan_id' => $slotPlan->id,
            'sequence' => $sequence,
        ]);
    }

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet]);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$openingScores[0]->id, $openingScores[1]->id, $communionScores[0]->id]);
});

/**
 * Put a music in a collection at a given number, the way an editor would.
 */
function musicInCollection(int $musicId, string $abbreviation, ?string $orderNumber = null, int $priority = 100): CollectionModel
{
    // The abbreviations are the real ones, and the test database may already hold
    // them: an abbreviation names one book, so the one that is there is used.
    $collection = CollectionModel::query()->firstOrNew(['abbreviation' => $abbreviation]);

    if (! $collection->exists) {
        $collection = CollectionModel::factory()->make(['abbreviation' => $abbreviation]);
    }

    // Ranked by the priority given here, so a reference naming several books is
    // asserted in the order it will really be printed in.
    $collection->forceFill(['is_private' => false, 'is_verified' => false, 'priority' => $priority])->save();
    $collection->genres()->detach();

    Music::findOrFail($musicId)->collections()->attach($collection->id, ['order_number' => $orderNumber]);

    return $collection;
}

it('prints where the music can be looked up beside its name', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    musicInCollection($scores[0]->music_id, 'DÚR', '47', priority: 10);
    musicInCollection($scores[0]->music_id, 'ÉE', '131', priority: 20);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleMusicCollections', $booklet->entries()->firstOrFail()->id);

    $payload = payloadOf($booklet);

    // One parenthesis, one word per collection, a single space between them: it
    // is a reference and not a sentence, and rides on the line that names the
    // music rather than taking one of its own.
    expect($payload[0]['reference'])->toBe('(DÚR47 ÉE131)')
        ->and($payload[0]['slot'])->toBe('Kezdőének – Áldjad, én lelkem');
});

it('says where the music can be looked up once, over the row that opens it', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    musicInCollection($scores[0]->music_id, 'ÉE', '232B');

    $organ = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'music_id' => $scores[0]->music_id,
        'title' => 'Áldjad, én lelkem',
    ]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        ->call('toggleScore', $organ->id, $assignments[0]->id);

    // The switch is the opening row's, and so is the line it prints: the second
    // engraving of the same music says nothing about where to look it up.
    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleMusicCollections', $booklet->entries()->orderBy('sequence')->firstOrFail()->id);

    $payload = payloadOf($booklet);

    expect($payload[0]['reference'])->toBe('(ÉE232B)')
        ->and($payload[1]['reference'])->toBeNull();
});

it('says nothing about the collections until a row is asked for them', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    musicInCollection($scores[0]->music_id, 'DÚR', '47');

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    // A booklet is printed because the books it would point at are not in every
    // hand, so the numbers wait for the switch beside them in the plan — and the
    // music is named on the page either way.
    $payload = payloadOf($booklet);

    expect($payload[0]['reference'])->toBeNull()
        ->and($payload[0]['slot'])->toBe('Kezdőének – Áldjad, én lelkem');

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleMusicCollections', $booklet->entries()->firstOrFail()->id);

    expect(payloadOf($booklet)[0]['reference'])->toBe('(DÚR47)');
});

it('shows where the music can be looked up in the plan, chosen or not', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, , $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    musicInCollection($scores[0]->music_id, 'KÉK', '23');

    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => $booklet])->html();

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

    $reference = (new DOMXPath($document))->query('//*[@data-plan-music-reference]')->item(0);

    expect($reference)->not->toBeNull()
        ->and(trim($reference->textContent))->toBe('(KÉK23)');
});

it('makes the reference itself the switch that prints it', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    musicInCollection($scores[0]->music_id, 'KÉK', '23');

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    // The thing that is printed is the thing that is clicked, so there is no
    // third eye on the row to tell apart from the two that govern the names.
    $pressed = fn (string $html): ?string => referenceButton($html)?->getAttribute('aria-pressed');

    expect($pressed($component->html()))->toBe('false');

    $component->call('toggleMusicCollections', $booklet->entries()->firstOrFail()->id);

    expect($pressed($component->html()))->toBe('true');
});

/**
 * The plan's reference switch, if it is one.
 */
function referenceButton(string $html): ?DOMElement
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

    $element = (new DOMXPath($document))->query('//button[@data-plan-music-reference]')->item(0);

    return $element instanceof DOMElement ? $element : null;
}
