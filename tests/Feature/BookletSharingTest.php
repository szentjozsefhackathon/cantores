<?php

use App\Livewire\Pages\BookletEditor;
use App\Livewire\Pages\BookletLoanView;
use App\Livewire\Pages\Loans;
use App\Models\Booklet;
use App\Models\BookletScore;
use App\Models\Loan;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Models\User;
use App\Services\LoanKeepingService;
use App\Services\ScoreFileStorage;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Handing the booklet to the band.
 *
 * The link is a Loan like every other on the site, so what it reaches is derived
 * from it on every request rather than minted onto anything: recalling it closes
 * the pages and the systems under them at once. What it must never do is reach
 * further than the handout — a booklet link opens the scores in the booklet, and
 * nothing else its owner happens to own.
 */

// What the link opens, not who may open it — the Turnstile gate is tested apart.
beforeEach(function () {
    passHumanCheck();
});

function sharedBooklet(User $owner, ?Score $score = null): array
{
    $booklet = Booklet::factory()->create(['user_id' => $owner->id]);

    if ($score instanceof Score) {
        BookletScore::factory()->create([
            'booklet_id' => $booklet->id,
            'score_id' => $score->id,
            'sequence' => 1,
        ]);
    }

    return [$booklet, Loan::factory()->of($booklet)->create()];
}

it('mints a link from the editor and recalls it again', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->assertSet('shareUrl', null)
        ->call('lendByLink');

    $token = $booklet->fresh()->loanToken();

    expect($token)->not->toBeNull();

    $component->assertSet('shareUrl', route('booklet.loan', ['token' => $token]));

    $component->call('recallLoan')->assertSet('shareUrl', null);

    expect($booklet->fresh()->loanToken())->toBeNull();
});

it('refuses to lend somebody elses booklet', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $owner->id]);

    actingAs($stranger);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])->assertForbidden();
});

it('opens the booklet to a guest holding the link', function () {
    $owner = User::factory()->create(['name' => 'Kántor']);
    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    [$booklet, $loan] = sharedBooklet($owner, $score);

    get(route('booklet.loan', ['token' => $loan->token]))
        ->assertOk()
        ->assertSee($booklet->title);
});

it('returns 404 for an unknown, revoked or wrongly typed token', function () {
    $owner = User::factory()->create();
    [, $loan] = sharedBooklet($owner);

    get(route('booklet.loan', ['token' => 'nonexistenttoken12345678901234']))->assertNotFound();

    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    $scoreLoan = Loan::factory()->of($score)->create();

    // A score's own token is not a booklet token, however live it is.
    get(route('booklet.loan', ['token' => $scoreLoan->token]))->assertNotFound();

    $loan->revoke();

    get(route('booklet.loan', ['token' => $loan->token]))->assertNotFound();
});

// The whole point of a link rather than a PDF: the leader writes the second verse
// in during the rehearsal, and the band has it without a new file being sent.
it('shows the booklet as it stands now, not as it stood when the link was made', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    [$booklet, $loan] = sharedBooklet($owner);

    $view = Livewire::test(BookletLoanView::class, ['token' => $loan->token]);

    expect($view->get('entries'))->toBeEmpty();

    BookletScore::factory()->create([
        'booklet_id' => $booklet->id,
        'score_id' => $score->id,
        'sequence' => 1,
    ]);
    $booklet->update(['title' => 'Vasárnapi füzet']);

    $view->call('reload')
        ->assertSet('title', 'Vasárnapi füzet')
        ->assertDispatched('booklet-updated');

    expect($view->get('entries'))->toHaveCount(1)
        ->and($view->get('entries')[0]['kind'])->toBe('score');
});

// The reader is entitled by the link, not by an account: a guest gets the private
// score in the booklet, because that is what the cantor lent them.
it('draws a private score to a guest on the link, and stops when it is recalled', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    [, $loan] = sharedBooklet($owner, $score);

    $view = Livewire::test(BookletLoanView::class, ['token' => $loan->token]);

    expect($view->get('entries'))->toHaveCount(1)
        ->and($view->get('entries')[0]['content'])->toBe($score->content);

    $loan->revoke();

    Livewire::test(BookletLoanView::class, ['token' => $loan->token])->assertNotFound();
});

// A booklet is only ever a route to what it prints. Everything else its owner owns
// stays behind the link, which is why the loan chain answers with the entries
// rather than with the owner's library.
it('reaches only the scores the booklet actually prints', function () {
    $owner = User::factory()->create();
    $printed = Score::factory()->abc()->create(['user_id' => $owner->id]);
    $unprinted = Score::factory()->abc()->create(['user_id' => $owner->id]);
    [, $loan] = sharedBooklet($owner, $printed);

    $reached = app(App\Services\LoanAccessService::class)->scoreIdsFor($loan);

    expect($reached)->toContain($printed->id)
        ->and($reached)->not->toContain($unprinted->id);
});

// A score the cantor borrowed travels with the handout only while their own right
// to it holds — the same rule a lent folder follows.
it('stops passing on a borrowed score once the loan behind it is recalled', function () {
    $lender = User::factory()->create();
    $cantor = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $lender->id]);

    $behind = Loan::factory()->of($score)->create();
    app(LoanKeepingService::class)->keep($behind, $cantor);

    [, $loan] = sharedBooklet($cantor, $score);

    expect(app(App\Services\LoanAccessService::class)->scoreIdsFor($loan))->toContain($score->id);

    $behind->revoke();

    expect(app(App\Services\LoanAccessService::class)->scoreIdsFor($loan))->not->toContain($score->id);
});

it('serves an uploaded system through the link, and refuses one the booklet does not print', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $printed = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $file = ScoreFile::factory()->banded()->create(['score_id' => $printed->id]);

    $elsewhere = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    ScorePublication::factory()->approved()->create(['score_id' => $elsewhere->id]);
    $otherFile = ScoreFile::factory()->banded()->create(['score_id' => $elsewhere->id]);

    app(ScoreFileStorage::class)->put($file->stripPath(1, 1), 'strip bytes');
    app(ScoreFileStorage::class)->put($otherFile->stripPath(1, 1), 'strip bytes');

    [, $loan] = sharedBooklet($owner, $printed);

    get(route('booklet.loan.strip', [
        'token' => $loan->token, 'scoreFile' => $file->id, 'page' => 1, 'index' => 1,
    ]))->assertOk()->assertHeader('Content-Type', 'image/png');

    // Published, so the reader could read it elsewhere — but this link is a
    // handout, and it opens the handout.
    get(route('booklet.loan.strip', [
        'token' => $loan->token, 'scoreFile' => $otherFile->id, 'page' => 1, 'index' => 1,
    ]))->assertNotFound();
});

it('serves an engraved page through the link and stops at the recall', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $file = ScoreFile::factory()->vector()->create(['score_id' => $score->id]);

    app(ScoreFileStorage::class)->put($file->pageVectorPath(1), gzencode('<svg xmlns="http://www.w3.org/2000/svg"/>'));

    [, $loan] = sharedBooklet($owner, $score);

    $url = route('booklet.loan.score-page', [
        'token' => $loan->token, 'scoreFile' => $file->id, 'page' => 1,
    ]);

    get($url)->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

    $loan->revoke();

    get($url)->assertNotFound();
});

// The reader draws the uploaded systems too, so the payload has to address them
// through the token rather than through the owner's own routes, which would 403.
it('addresses uploaded systems through the token it was read on', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $score = Score::factory()->linksOnly()->create(['user_id' => $owner->id]);
    $file = ScoreFile::factory()->banded()->create(['score_id' => $score->id]);
    [, $loan] = sharedBooklet($owner, $score);

    $entries = Livewire::test(BookletLoanView::class, ['token' => $loan->token])->get('entries');

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['kind'])->toBe('file')
        ->and($entries[0]['strips'][0]['url'])->toContain('/b/'.$loan->token.'/strip/');
});

// The two views are one document. Whatever the editor would print, the link shows.
it('gives the reader the same pages the editor draws', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    [$booklet, $loan] = sharedBooklet($owner, $score);

    actingAs($owner);
    $editing = Livewire::test(BookletEditor::class, ['booklet' => $booklet])->get('renderPayload');

    auth()->logout();
    $reading = Livewire::test(BookletLoanView::class, ['token' => $loan->token])->get('entries');

    expect($reading)->toEqual($editing);
});

it('offers no way to download the booklet it is reading', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    [, $loan] = sharedBooklet($owner, $score);

    get(route('booklet.loan', ['token' => $loan->token]))
        ->assertOk()
        ->assertDontSee(route('booklets.export-pdf', ['booklet' => 1]))
        ->assertDontSee(__('Download PDF'));
});

// The reader's toolbar is not the editor's. A cantor at a desk is fitting a pile
// of scores onto A5; a musician is holding a phone at a music stand with one
// hand, and everything about the page — its width, the face, the numbers behind
// the knobs — is either the screen's business or settled once at the top.
it('offers the reader two knobs on a score rather than the cantors whole panel', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    [, $loan] = sharedBooklet($owner, $score);

    $panels = Livewire::test(BookletLoanView::class, ['token' => $loan->token])->instance()->panels();

    expect(array_column($panels['abc'], 'key'))->toBe(['abcLyricSize', 'abcTranspose'])
        ->and(array_column($panels['gabc'], 'key'))->toBe(['lyricSize'])
        ->and(array_column($panels['chordpro'], 'key'))->toBe(['chordproFontSize', 'chordproTranspose'])
        ->and(array_column($panels['aretino'], 'key'))->toBe(['aretinoLyricSize'])
        ->and(array_column($panels['file'], 'key'))->toBe(['fileZoom']);

    $keys = collect($panels)->flatten(1)->pluck('key')->all();

    expect($keys)->not->toContain('abcPageWidth', 'gabcLayoutWidth', 'aretinoStaffWidth')
        ->and($keys)->not->toContain('abcLyricFont', 'lyricFont', 'chordproFontFamily', 'aretinoTextFont');

    // Half a unit a press, whatever step the same knob takes in the editor.
    expect(collect($panels['abc'])->firstWhere('key', 'abcLyricSize'))
        ->step->toBe(0.5)
        ->role->toBe('size');
});

// One button, and it takes the whole scrolling booklet: what a phone on a music
// stand is short of is the browser's bars and the site's own menu, not a way to
// blow up one engraving at a time.
it('offers one full screen for the whole booklet', function () {
    $owner = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $owner->id]);

    foreach ([1, 2] as $sequence) {
        BookletScore::factory()->create([
            'booklet_id' => $booklet->id,
            'score_id' => Score::factory()->abc()->create(['user_id' => $owner->id])->id,
            'sequence' => $sequence,
        ]);
    }

    $loan = Loan::factory()->of($booklet)->create();

    $html = get(route('booklet.loan', ['token' => $loan->token]))->assertOk()->getContent();

    expect(substr_count($html, 'aria-label="'.__('Full screen').'"'))->toBe(1)
        ->and(substr_count($html, 'aria-label="'.__('Leave full screen').'"'))->toBe(1)
        // The two scores are each still adjustable, so the count above is about
        // the full screen alone.
        ->and(substr_count($html, 'aria-label="'.__('Adjust this score').'"'))->toBe(2);
});

it('takes its lending links with it when the booklet is deleted', function () {
    $owner = User::factory()->create();
    [$booklet, $loan] = sharedBooklet($owner);

    $booklet->delete();

    expect(Loan::query()->whereKey($loan->id)->exists())->toBeFalse();
});

// A link handed out months ago has to be findable and recallable somewhere other
// than the editor it was made in — which is the whole reason the lending centre
// exists, and why a booklet loan has to be a citizen of it rather than an
// "Unknown" row pointing at a score route.
it('lists the booklet link in the lending centre, under its own name', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    [$booklet, $loan] = sharedBooklet($owner, $score);

    $booklet->update(['title' => 'Pünkösdi füzet']);

    actingAs($owner);

    Livewire::test(Loans::class)
        ->call('selectTab', 'lent')
        ->assertSee('Pünkösdi füzet')
        ->assertSee(route('booklet.loan', ['token' => $loan->token]));
});
