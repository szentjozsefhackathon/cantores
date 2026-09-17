<?php

use App\Http\Controllers\BookletController;
use App\Http\Controllers\BookletLoanScorePageController;
use App\Http\Controllers\BookletLoanStripController;
use App\Http\Controllers\BookletPdfExportController;
use App\Http\Controllers\BookletScorePageController;
use App\Http\Controllers\BookletStripController;
use App\Http\Controllers\HumanCheckController;
use App\Http\Controllers\MusicPlanController;
use App\Http\Controllers\PresentationPayloadController;
use App\Http\Controllers\PresentationStateController;
use App\Http\Controllers\ProjectionAddedMusicController;
use App\Http\Controllers\ProjectionController;
use App\Http\Controllers\ProjectionMoveController;
use App\Http\Controllers\ProjectionMusicSearchController;
use App\Http\Controllers\ProjectionScorePageController;
use App\Http\Controllers\ProjectionScoreToggleController;
use App\Http\Controllers\PublicScoreDownloadController;
use App\Http\Controllers\PublicScorePageController;
use App\Http\Controllers\QrLoginClaimController;
use App\Http\Controllers\ScoreFileDownloadController;
use App\Http\Controllers\ScoreFilePageController;
use App\Http\Controllers\ScoreFileThumbnailController;
use App\Http\Controllers\ScoreIncipitController;
use App\Http\Controllers\ScoreLoanFileDownloadController;
use App\Http\Controllers\ScoreLoanFilePageController;
use App\Http\Controllers\ScoreLoanIncipitController;
use App\Http\Controllers\ScorePdfExportController;
use App\Http\Controllers\ScorePublicIncipitController;
use App\Http\Controllers\ScreenFitController;
use App\Http\Controllers\ShowStateController;
use App\Http\Controllers\SitemapController;
use App\Livewire\Pages\AbcGuide;
use App\Livewire\Pages\AretinoGuide;
use App\Livewire\Pages\AuthorView;
use App\Livewire\Pages\BookletEditor;
use App\Livewire\Pages\BookletLoanView;
use App\Livewire\Pages\CollectionView;
use App\Livewire\Pages\Editor\Authors;
use App\Livewire\Pages\Editor\ExternalLinks;
use App\Livewire\Pages\Editor\Musics;
use App\Livewire\Pages\Editor\MusicTagManager;
use App\Livewire\Pages\Editor\MusicVerifier;
use App\Livewire\Pages\Editor\ScorePublicationReview;
use App\Livewire\Pages\FolderEditor;
use App\Livewire\Pages\Folders;
use App\Livewire\Pages\FolderView;
use App\Livewire\Pages\LoanManager;
use App\Livewire\Pages\Loans;
use App\Livewire\Pages\MusicPlanLoanView;
use App\Livewire\Pages\MusicView;
use App\Livewire\Pages\MyMusicPlans;
use App\Livewire\Pages\Notifications;
use App\Livewire\Pages\PlanDocuments;
use App\Livewire\Pages\ProjectionEditor;
use App\Livewire\Pages\ProjectionPresenter;
use App\Livewire\Pages\ProjectionRemote;
use App\Livewire\Pages\ProjectionRemoteDecks;
use App\Livewire\Pages\PublicScores;
use App\Livewire\Pages\PublicScoreView;
use App\Livewire\Pages\QrLogin;
use App\Livewire\Pages\QrLoginApproval;
use App\Livewire\Pages\ScoreEditor;
use App\Livewire\Pages\Scores;
use App\Livewire\Pages\ScoreView;
use App\Models\City;
use App\Models\DirektoriumEdition;
use App\Models\FirstName;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('/about', 'pages.about')->name('about');

Route::view('/guide', 'pages.guide')->name('guide');

Route::livewire('/aretino/guide', AretinoGuide::class)
    ->name('aretino.guide');

Route::livewire('/abc/guide', AbcGuide::class)
    ->name('abc.guide');

// Music database landing page (public)
Route::livewire('/music-database', 'pages::music-database')
    ->name('music-database');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');

// What may be published in the free library, and how to report a problem.
Route::view('/kotta-jogok', 'pages.kotta-jogok')->name('score-rights');

Route::get('/random-nickname', function () {
    $cities = City::allCached();
    $firstNames = FirstName::allCached();

    // Get used combinations
    $usedCombinations = User::select('city_id', 'first_name_id')
        ->get()
        ->map(fn ($user) => $user->city_id.'_'.$user->first_name_id)
        ->toArray();

    $availableCombinations = [];

    foreach ($cities as $city) {
        foreach ($firstNames as $firstName) {
            $key = $city->id.'_'.$firstName->id;
            if (! in_array($key, $usedCombinations)) {
                $availableCombinations[] = ['city_id' => $city->id, 'first_name_id' => $firstName->id];
            }
        }
    }

    if (! empty($availableCombinations)) {
        $random = $availableCombinations[array_rand($availableCombinations)];
        $selectedCityId = $random['city_id'];
        $selectedFirstNameId = $random['first_name_id'];
    } else {
        // If all combinations are used, fallback to random city and first name
        $selectedCityId = $cities->isNotEmpty() ? $cities->random()->id : null;
        $selectedFirstNameId = $firstNames->isNotEmpty() ? $firstNames->random()->id : null;
    }

    return response()->json([
        'city_id' => $selectedCityId,
        'first_name_id' => $selectedFirstNameId,
    ]);
})->name('random-nickname');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';

// Music plan creation (POST)
Route::post('/music-plans', [MusicPlanController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('music-plans.store');

// Music plan copy (POST)
Route::post('/music-plans/{musicPlan}/copy', [MusicPlanController::class, 'copy'])
    ->middleware(['auth', 'verified'])
    ->name('music-plans.copy');

// Music plan editor - with optional parameter for existing plans
Route::livewire('/music-plan/{musicPlan?}', 'pages::music-plan.music-plan-editor')
    ->middleware(['auth', 'verified'])
    ->name('music-plan-editor');

// Music plan view - read-only display (public for published plans)
Route::livewire('/music-plan/{musicPlan}/view', 'pages::music-plan.music-plan-view')
    ->name('music-plan-view');

// Music plans list (authenticated user's own plans)
Route::livewire('/my-music-plans', MyMusicPlans::class)
    ->middleware(['auth', 'verified'])
    ->name('my-music-plans');

// Public music plans listing (guest accessible)
Route::livewire('/music-plans', 'pages::music-plans')
    ->name('music-plans');

// Collections landing page (public)
Route::livewire('/collections', 'pages::collections-landing')
    ->name('collections');

// Public read-only collection view
Route::livewire('/collection/{collection}/view', CollectionView::class)
    ->name('collection-view');

// Authors landing page (public)
Route::livewire('/authors', 'pages::authors-landing')
    ->name('authors');

// Authors editor (browseable by guests, edit actions require auth)
Route::livewire('/authors/editor', Authors::class)
    ->name('authors-editor');

// Public read-only author view
Route::livewire('/author/{author}/view', AuthorView::class)
    ->name('author-view');

Route::livewire('/musics', Musics::class)
    ->name('musics');

// Shared score preview — public, no authentication required
Route::livewire('/score/preview', ScoreEditor::class)
    ->name('score.preview');

// SVG → PDF export — public (guests may export) but protected by CSRF and rate limiting
Route::post('/score/export-pdf', ScorePdfExportController::class)
    ->middleware('throttle:20,1')
    ->name('score.export-pdf');

// Every lending link — a bearer URL anyone holding it may read — sits behind the
// human check. Borrowing is between people, so a guest proves as much once per
// session before any of these open. See EnsureVisitorIsHuman.
Route::middleware('human')->group(function (): void {
    // Lending link — public, resolves to edit for owner or read-only for others
    Route::livewire('/s/{token}', ScoreView::class)
        ->name('score.loan');

    Route::get('/s/{token}/incipit', ScoreLoanIncipitController::class)
        ->name('score.loan.incipit');

    // A score reached *through* a loan — the score itself, or a folder or plan that
    // reaches it. Access is derived from the loan on every request, so revoking the
    // loan revokes these URLs too. The /share/ prefix is left alone: these URLs are
    // bearer links already in circulation.
    Route::livewire('/share/{token}/score/{score}', ScoreView::class)
        ->name('loan.score');

    Route::get('/share/{token}/score/{score}/incipit', ScoreLoanIncipitController::class)
        ->name('loan.score.incipit');

    // Rendered pages and the original file of an uploaded score, reached through a
    // loan. A directly lent score is its own loan, so these serve every kind of
    // link uniformly.
    Route::get('/share/{token}/score/{score}/file/{scoreFile}/page/{page}', ScoreLoanFilePageController::class)
        ->whereNumber('page')
        ->name('loan.score.file.page');

    Route::get('/share/{token}/score/{score}/file/{scoreFile}/download', ScoreLoanFileDownloadController::class)
        ->name('loan.score.file.download');

    // Plan lending link — public, no authentication required
    Route::livewire('/p/{token}', MusicPlanLoanView::class)
        ->name('music-plan.loan');

    // Booklet lending link — the handout as the band reads it, on their own
    // phones. The same booklet the editor is showing, re-engraved to the width
    // of whatever screen it lands on, so a chord changed at the rehearsal is
    // there on the next refresh.
    Route::livewire('/b/{token}', BookletLoanView::class)
        ->name('booklet.loan');

    // The uploaded systems and pages that booklet draws, addressed by the token
    // rather than by the booklet: same two endpoints as the owner's, same check
    // underneath, and the reader's entitlement derived from the link on every
    // request. See BookletRenderPayload::drawsFile().
    Route::get('/b/{token}/strip/{scoreFile}/{page}/{index}', BookletLoanStripController::class)
        ->whereNumber(['page', 'index'])
        ->name('booklet.loan.strip');

    Route::get('/b/{token}/score-page/{scoreFile}/{page}', BookletLoanScorePageController::class)
        ->whereNumber('page')
        ->name('booklet.loan.score-page');
});

// The human check itself: guests only, rate limited because it is the one page a
// crawler that found a lending link is allowed to reach.
Route::get('/emberi-ellenorzes', [HumanCheckController::class, 'show'])
    ->name('human-check');

Route::post('/emberi-ellenorzes', [HumanCheckController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('human-check.store');

// Signing a borrowed screen in from a phone. The laptop at the church is already
// set up and belongs to nobody; typing an account into it in front of the
// congregation is the friction this removes.
//
// `claim` is declared before `{token}` and the token is constrained besides, so
// the one cannot swallow the other.
Route::get('/qr/claim', QrLoginClaimController::class)
    ->middleware('throttle:30,1')
    ->name('qr-login.claim');

Route::livewire('/qr', QrLogin::class)
    ->middleware('throttle:30,1')
    ->name('qr-login');

// The phone's side: behind `auth`, so a signed-out phone is carried through the
// login form and back by `url.intended` with no help from us.
Route::livewire('/qr/{token}', QrLoginApproval::class)
    ->where('token', '[A-Za-z0-9]{32}')
    ->middleware(['auth', 'verified'])
    ->name('qr-login.approve');

Route::livewire('/scores', Scores::class)
    ->middleware(['auth', 'verified'])
    ->name('scores');

Route::livewire('/scores/create/{music?}', ScoreEditor::class)
    ->middleware(['auth', 'verified'])
    ->name('scores.create');

Route::livewire('/scores/{score}/edit', ScoreEditor::class)
    ->middleware(['auth', 'verified'])
    ->name('scores.edit');

Route::get('/scores/{score}/incipit', ScoreIncipitController::class)
    ->middleware(['auth', 'verified'])
    ->name('scores.incipit');

Route::get('/scores/{score}/public-incipit', ScorePublicIncipitController::class)
    ->name('scores.public-incipit');

Route::get('/scores/{score}/file/{scoreFile}/page/{page}', ScoreFilePageController::class)
    ->middleware(['auth', 'verified'])
    ->whereNumber('page')
    ->name('scores.file.page');

Route::get('/scores/{score}/file/{scoreFile}/thumbnail', ScoreFileThumbnailController::class)
    ->middleware(['auth', 'verified'])
    ->name('scores.file.thumbnail');

Route::get('/scores/{score}/file/{scoreFile}/download', ScoreFileDownloadController::class)
    ->middleware(['auth', 'verified'])
    ->name('scores.file.download');

// Public score library — indexable, no authentication. These routes are kept
// separate from the /scores/* ones on purpose: those sit behind auth,verified,
// and that middleware is a second line of defence over every private file.
// PublicScoreAccessService is the single gate here.
Route::livewire('/ingyenes-kottak', PublicScores::class)
    ->name('public-scores');

Route::livewire('/ingyenes-kottak/{score}/{slug?}', PublicScoreView::class)
    ->name('public-scores.show');

Route::get('/ingyenes-kottak/{score}/file/{scoreFile}/page/{page}', PublicScorePageController::class)
    ->whereNumber('page')
    ->name('public-scores.file.page');

Route::get('/ingyenes-kottak/{score}/file/{scoreFile}/download', PublicScoreDownloadController::class)
    ->middleware('throttle:60,1')
    ->name('public-scores.file.download');

// Folder lending link — public, read-only, behind the human check like every
// other lending link
Route::livewire('/f/{token}', FolderView::class)
    ->middleware('human')
    ->name('folder.loan');

Route::livewire('/folders', Folders::class)
    ->middleware(['auth', 'verified'])
    ->name('folders');

// The lending centre: what I borrowed, what I lent, and what I published
Route::livewire('/kolcsonzesek', Loans::class)
    ->middleware(['auth', 'verified'])
    ->name('loans');

// Which scores a lent folder or plan actually opens
Route::livewire('/kolcsonzesek/{loan}', LoanManager::class)
    ->middleware(['auth', 'verified'])
    ->name('loans.manage');

// The screen was called /shared-links before lending got its own vocabulary
Route::redirect('/shared-links', '/kolcsonzesek');

Route::livewire('/folders/create', FolderEditor::class)
    ->middleware(['auth', 'verified'])
    ->name('folders.create');

Route::livewire('/folders/{folder}/edit', FolderEditor::class)
    ->middleware(['auth', 'verified'])
    ->name('folders.edit');

// Everything a plan has been made into, booklets and projections together, one
// service to a row: the two used to have a list each, and nothing then said
// which deck belonged with which booklet.
Route::livewire('/plan-documents', PlanDocuments::class)
    ->middleware(['auth', 'verified'])
    ->name('plan-documents');

// The two screens that list was made out of
Route::redirect('/booklets', '/plan-documents');
Route::redirect('/projections', '/plan-documents');

// Booklets: a music plan's scores laid onto real A4 or A5 pages. The editor
// chooses and arranges; the pages themselves are engraved in the browser and
// only come back here to be turned into a PDF.

Route::post('/booklets', [BookletController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('booklets.store');

Route::livewire('/booklets/{booklet}/edit', BookletEditor::class)
    ->middleware(['auth', 'verified'])
    ->name('booklets.edit');

Route::delete('/booklets/{booklet}', [BookletController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('booklets.destroy');

Route::post('/booklets/{booklet}/export-pdf', BookletPdfExportController::class)
    ->middleware(['auth', 'verified', 'throttle:20,1'])
    ->name('booklets.export-pdf');

// One system of an uploaded score, for a booklet that flows systems rather than
// pages. The booklet is in the path because it is the booklet's access to the
// score that is being checked.
Route::get('/booklets/{booklet}/strip/{scoreFile}/{page}/{index}', BookletStripController::class)
    ->whereNumber(['page', 'index'])
    ->middleware(['auth', 'verified'])
    ->name('booklets.strip');

// One engraved page in vector form, for a booklet drawing a file whose systems
// are windows onto it rather than cut-out images. One request per page: a
// four-system page is fetched once. Same access question as the strip route.
Route::get('/booklets/{booklet}/score-page/{scoreFile}/{page}', BookletScorePageController::class)
    ->whereNumber('page')
    ->middleware(['auth', 'verified'])
    ->name('booklets.score-page');

// Projections: a music plan's scores cut into slides for the screen the
// congregation reads from — the other half of what a booklet is. The editor
// chooses and arranges; the slides themselves are engraved in the browser, from
// each score's own layout for this ratio.
Route::post('/projections', [ProjectionController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('projections.store');

Route::livewire('/projections/{projection}/edit', ProjectionEditor::class)
    ->middleware(['auth', 'verified'])
    ->name('projections.edit');

// The deck as the room sees it: one slide at a time, full screen, driven from
// the keyboard. Its own page rather than a mode of the editor, so the person at
// the keyboard can put it on the projector and nothing else.
Route::livewire('/projections/{projection}/present', ProjectionPresenter::class)
    ->middleware(['auth', 'verified'])
    ->name('projections.present');

// The screen itself: a browser facing the room, waiting to be given a deck.
//
// The parish laptop opens this once at the start of Mass and is not touched
// again — everything after that is done from the phone. Opening it is the whole
// of what claiming a screen means: both devices were already the same person, so
// what the phone lacked was never permission but an address.
Route::livewire('/present', ProjectionPresenter::class)
    ->middleware(['auth', 'verified'])
    ->name('projection-screen');

// This person's show: read it, and put another deck up. The read is polled about
// once a second by the wall and the phone alike, and carries the presentation's
// own state nested inside, so that following the show and following the service
// are one request rather than two. No screen in the address: every device of a
// person's follows the same show.
Route::get('/show/state', [ShowStateController::class, 'show'])
    ->middleware(['auth', 'verified', 'throttle:projection-poll'])
    ->name('show.state');

Route::post('/show/state', [ShowStateController::class, 'update'])
    ->middleware(['auth', 'verified', 'throttle:projection-poll'])
    ->name('show.state.store');

// Where the picture lands on one wall — the one write still addressed to a
// device, because every projector is hung differently.
Route::post('/screens/{screen}/fit', ScreenFitController::class)
    ->middleware(['auth', 'verified', 'throttle:projection-poll'])
    ->name('screens.fit');

// Where a running deck has got to, as the two devices driving it agree on it.
//
// Plain JSON rather than a Livewire round trip: the presenter's stage is
// wire:ignore'd and its component writes nothing, so that no re-render can touch
// the picture during a service, and polling the component would give that up.
// These are polled about once a second from both ends while a Mass is going on.
Route::get('/presentations/{presentation}/state', [PresentationStateController::class, 'show'])
    ->middleware(['auth', 'verified', 'throttle:projection-poll'])
    ->name('presentations.state');

Route::post('/presentations/{presentation}/state', [PresentationStateController::class, 'update'])
    ->middleware(['auth', 'verified', 'throttle:projection-poll'])
    ->name('presentations.state.store');

// The deck itself, re-read when the state answer says it has moved underneath.
Route::get('/presentations/{presentation}/payload', PresentationPayloadController::class)
    ->middleware(['auth', 'verified', 'throttle:projection-payload'])
    ->name('presentations.payload');

// The show driven from a phone. The cantor is at the organ and the laptop is
// across the building, so the person who knows when to advance is never the
// person whose hand is on the keyboard.
Route::livewire('/remote', ProjectionRemote::class)
    ->middleware(['auth', 'verified'])
    ->name('projection-remote');

// Which deck to put up. Its own page rather than a mode of the control page, so
// that going back from it is ordinary navigation.
Route::livewire('/remote/decks', ProjectionRemoteDecks::class)
    ->middleware(['auth', 'verified'])
    ->name('projection-remote.decks');

// The remote used to be addressed to a screen, and phones keep bookmarks.
Route::redirect('/remote/{screen}', '/remote')->whereNumber('screen');
Route::redirect('/remote/{screen}/decks', '/remote/decks')->whereNumber('screen');

Route::delete('/projections/{projection}', [ProjectionController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('projections.destroy');

// One page of an uploaded score, for a deck that shows a scan a page at a time.
// The projection is in the path because it is the projection's access to the
// score that is being checked.
Route::get('/projections/{projection}/score-page/{scoreFile}/{page}', ProjectionScorePageController::class)
    ->whereNumber('page')
    ->middleware(['auth', 'verified'])
    ->name('projections.score-page');

// The remote's own way to add one of a music's other engravings to the deck, or
// take one back out — the same write the editor's plan pane does, reached from
// the phone driving the service instead.
Route::post('/projections/{projection}/score-toggle', ProjectionScoreToggleController::class)
    ->middleware(['auth', 'verified', 'throttle:projection-payload'])
    ->name('projections.score-toggle');

// The rest of what the remote may change for good: a music the plan does not
// have, added or taken away, and any row, music or slot moved into place. Each
// answers with the deck made fresh, like the toggle.
Route::get('/projections/{projection}/music-search', ProjectionMusicSearchController::class)
    ->middleware(['auth', 'verified', 'throttle:projection-payload'])
    ->name('projections.music-search');

Route::post('/projections/{projection}/added-musics', [ProjectionAddedMusicController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:projection-payload'])
    ->name('projections.added-musics.store');

Route::delete('/projections/{projection}/added-musics/{addedMusic}', [ProjectionAddedMusicController::class, 'destroy'])
    ->middleware(['auth', 'verified', 'throttle:projection-payload'])
    ->scopeBindings()
    ->name('projections.added-musics.destroy');

Route::post('/projections/{projection}/move', ProjectionMoveController::class)
    ->middleware(['auth', 'verified', 'throttle:projection-payload'])
    ->name('projections.move');

Route::livewire('/music/{music}', 'pages::editor.music-editor')
    ->middleware(['auth', 'verified'])
    ->name('music-editor');

// Public read-only music view
Route::livewire('/music/{music}/view', MusicView::class)
    ->name('music-view');

// Music merging tool
Route::livewire('/editor/musics/merge', 'editor.music-merger')
    ->middleware(['auth', 'verified'])
    ->name('music-merger');

// Duplicate music merging tool
Route::livewire('/editor/musics/duplicates', 'editor.duplicate-merger')
    ->middleware(['auth', 'verified'])
    ->name('duplicate-merger');

// Music verification tool
Route::livewire('/editor/musics/verify', MusicVerifier::class)
    ->middleware(['auth', 'verified'])
    ->name('music-verifier');

// Score publication review queue — the gate on the public library
Route::livewire('/editor/score-publications', ScorePublicationReview::class)
    ->middleware(['auth', 'verified'])
    ->name('score-publication-review');

// Music tag manager tool
Route::livewire('/editor/music-tags', MusicTagManager::class)
    ->middleware(['auth', 'verified'])
    ->name('music-tag-manager');

// External links manager tool
Route::livewire('/editor/external-links', ExternalLinks::class)
    ->middleware(['auth', 'verified'])
    ->name('external-links');

// Suggestions page for music plan recommendations
Route::livewire('/suggestions', 'pages::suggestions')
    ->name('suggestions');

// Notifications page
Route::livewire('/notifications', Notifications::class)
    ->middleware(['auth', 'verified'])
    ->name('notifications');

// Contact us page
Route::livewire('/contact', 'contact-us')
    ->middleware(['auth', 'verified'])
    ->name('contact');

// Direktórium PDF page serving (auth-protected – copyright)
Route::get('/direktorium/{edition}/page/{page}', function (DirektoriumEdition $edition, int $page) {
    abort_if(! Storage::disk('private')->exists($edition->file_path), 404);
    abort_if($edition->total_pages && ($page < 1 || $page > $edition->total_pages), 404);

    $fullPath = Storage::disk('private')->path($edition->file_path);

    return response()->file($fullPath, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline',
    ]);
})->middleware(['auth', 'verified'])->name('direktorium.page');
