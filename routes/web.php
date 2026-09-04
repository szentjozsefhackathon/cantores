<?php

use Illuminate\Support\Facades\Route;

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

Route::livewire('/aretino/guide', \App\Livewire\Pages\AretinoGuide::class)
    ->name('aretino.guide');

Route::livewire('/abc/guide', \App\Livewire\Pages\AbcGuide::class)
    ->name('abc.guide');

// Music database landing page (public)
Route::livewire('/music-database', 'pages::music-database')
    ->name('music-database');

Route::get('/sitemap.xml', \App\Http\Controllers\SitemapController::class)->name('sitemap');

Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');

// What may be published in the free library, and how to report a problem.
Route::view('/kotta-jogok', 'pages.kotta-jogok')->name('score-rights');

Route::get('/random-nickname', function () {
    $cities = \App\Models\City::allCached();
    $firstNames = \App\Models\FirstName::allCached();

    // Get used combinations
    $usedCombinations = \App\Models\User::select('city_id', 'first_name_id')
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
Route::post('/music-plans', [\App\Http\Controllers\MusicPlanController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('music-plans.store');

// Music plan copy (POST)
Route::post('/music-plans/{musicPlan}/copy', [\App\Http\Controllers\MusicPlanController::class, 'copy'])
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
Route::livewire('/my-music-plans', \App\Livewire\Pages\MyMusicPlans::class)
    ->middleware(['auth', 'verified'])
    ->name('my-music-plans');

// Public music plans listing (guest accessible)
Route::livewire('/music-plans', 'pages::music-plans')
    ->name('music-plans');

// Collections landing page (public)
Route::livewire('/collections', 'pages::collections-landing')
    ->name('collections');

// Public read-only collection view
Route::livewire('/collection/{collection}/view', \App\Livewire\Pages\CollectionView::class)
    ->name('collection-view');

// Authors landing page (public)
Route::livewire('/authors', 'pages::authors-landing')
    ->name('authors');

// Authors editor (browseable by guests, edit actions require auth)
Route::livewire('/authors/editor', \App\Livewire\Pages\Editor\Authors::class)
    ->name('authors-editor');

// Public read-only author view
Route::livewire('/author/{author}/view', \App\Livewire\Pages\AuthorView::class)
    ->name('author-view');

Route::livewire('/musics', \App\Livewire\Pages\Editor\Musics::class)
    ->name('musics');

// Shared score preview — public, no authentication required
Route::livewire('/score/preview', \App\Livewire\Pages\ScoreEditor::class)
    ->name('score.preview');

// SVG → PDF export — public (guests may export) but protected by CSRF and rate limiting
Route::post('/score/export-pdf', \App\Http\Controllers\ScorePdfExportController::class)
    ->middleware('throttle:20,1')
    ->name('score.export-pdf');

// Lending link — public, resolves to edit for owner or read-only for others
Route::livewire('/s/{token}', \App\Livewire\Pages\ScoreView::class)
    ->name('score.loan');

Route::get('/s/{token}/incipit', \App\Http\Controllers\ScoreLoanIncipitController::class)
    ->name('score.loan.incipit');

// A score reached *through* a loan — the score itself, or a folder or plan that
// reaches it. Access is derived from the loan on every request, so revoking the
// loan revokes these URLs too. The /share/ prefix is left alone: these URLs are
// bearer links already in circulation.
Route::livewire('/share/{token}/score/{score}', \App\Livewire\Pages\ScoreView::class)
    ->name('loan.score');

Route::get('/share/{token}/score/{score}/incipit', \App\Http\Controllers\ScoreLoanIncipitController::class)
    ->name('loan.score.incipit');

// Rendered pages and the original file of an uploaded score, reached through a
// loan. A directly lent score is its own loan, so these serve every kind of
// link uniformly.
Route::get('/share/{token}/score/{score}/file/{scoreFile}/page/{page}', \App\Http\Controllers\ScoreLoanFilePageController::class)
    ->whereNumber('page')
    ->name('loan.score.file.page');

Route::get('/share/{token}/score/{score}/file/{scoreFile}/download', \App\Http\Controllers\ScoreLoanFileDownloadController::class)
    ->name('loan.score.file.download');

// Plan lending link — public, no authentication required
Route::livewire('/p/{token}', \App\Livewire\Pages\MusicPlanLoanView::class)
    ->name('music-plan.loan');

Route::livewire('/scores', \App\Livewire\Pages\Scores::class)
    ->middleware(['auth', 'verified'])
    ->name('scores');

Route::livewire('/scores/create/{music?}', \App\Livewire\Pages\ScoreEditor::class)
    ->middleware(['auth', 'verified'])
    ->name('scores.create');

Route::livewire('/scores/{score}/edit', \App\Livewire\Pages\ScoreEditor::class)
    ->middleware(['auth', 'verified'])
    ->name('scores.edit');

Route::get('/scores/{score}/incipit', \App\Http\Controllers\ScoreIncipitController::class)
    ->middleware(['auth', 'verified'])
    ->name('scores.incipit');

Route::get('/scores/{score}/public-incipit', \App\Http\Controllers\ScorePublicIncipitController::class)
    ->name('scores.public-incipit');

Route::get('/scores/{score}/file/{scoreFile}/page/{page}', \App\Http\Controllers\ScoreFilePageController::class)
    ->middleware(['auth', 'verified'])
    ->whereNumber('page')
    ->name('scores.file.page');

Route::get('/scores/{score}/file/{scoreFile}/thumbnail', \App\Http\Controllers\ScoreFileThumbnailController::class)
    ->middleware(['auth', 'verified'])
    ->name('scores.file.thumbnail');

Route::get('/scores/{score}/file/{scoreFile}/download', \App\Http\Controllers\ScoreFileDownloadController::class)
    ->middleware(['auth', 'verified'])
    ->name('scores.file.download');

// Public score library — indexable, no authentication. These routes are kept
// separate from the /scores/* ones on purpose: those sit behind auth,verified,
// and that middleware is a second line of defence over every private file.
// PublicScoreAccessService is the single gate here.
Route::livewire('/ingyenes-kottak', \App\Livewire\Pages\PublicScores::class)
    ->name('public-scores');

Route::livewire('/ingyenes-kottak/{score}/{slug?}', \App\Livewire\Pages\PublicScoreView::class)
    ->name('public-scores.show');

Route::get('/ingyenes-kottak/{score}/file/{scoreFile}/page/{page}', \App\Http\Controllers\PublicScorePageController::class)
    ->whereNumber('page')
    ->name('public-scores.file.page');

Route::get('/ingyenes-kottak/{score}/file/{scoreFile}/download', \App\Http\Controllers\PublicScoreDownloadController::class)
    ->middleware('throttle:60,1')
    ->name('public-scores.file.download');

// Folder lending link — public, read-only
Route::livewire('/f/{token}', \App\Livewire\Pages\FolderView::class)
    ->name('folder.loan');

Route::livewire('/folders', \App\Livewire\Pages\Folders::class)
    ->middleware(['auth', 'verified'])
    ->name('folders');

// The lending centre: what I borrowed, what I lent, and what I published
Route::livewire('/kolcsonzesek', \App\Livewire\Pages\Loans::class)
    ->middleware(['auth', 'verified'])
    ->name('loans');

// Which scores a lent folder or plan actually opens
Route::livewire('/kolcsonzesek/{loan}', \App\Livewire\Pages\LoanManager::class)
    ->middleware(['auth', 'verified'])
    ->name('loans.manage');

// The screen was called /shared-links before lending got its own vocabulary
Route::redirect('/shared-links', '/kolcsonzesek');

Route::livewire('/folders/create', \App\Livewire\Pages\FolderEditor::class)
    ->middleware(['auth', 'verified'])
    ->name('folders.create');

Route::livewire('/folders/{folder}/edit', \App\Livewire\Pages\FolderEditor::class)
    ->middleware(['auth', 'verified'])
    ->name('folders.edit');

Route::livewire('/music/{music}', 'pages::editor.music-editor')
    ->middleware(['auth', 'verified'])
    ->name('music-editor');

// Public read-only music view
Route::livewire('/music/{music}/view', \App\Livewire\Pages\MusicView::class)
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
Route::livewire('/editor/musics/verify', \App\Livewire\Pages\Editor\MusicVerifier::class)
    ->middleware(['auth', 'verified'])
    ->name('music-verifier');

// Score publication review queue — the gate on the public library
Route::livewire('/editor/score-publications', \App\Livewire\Pages\Editor\ScorePublicationReview::class)
    ->middleware(['auth', 'verified'])
    ->name('score-publication-review');

// Music tag manager tool
Route::livewire('/editor/music-tags', \App\Livewire\Pages\Editor\MusicTagManager::class)
    ->middleware(['auth', 'verified'])
    ->name('music-tag-manager');

// External links manager tool
Route::livewire('/editor/external-links', \App\Livewire\Pages\Editor\ExternalLinks::class)
    ->middleware(['auth', 'verified'])
    ->name('external-links');

// Suggestions page for music plan recommendations
Route::livewire('/suggestions', 'pages::suggestions')
    ->name('suggestions');

// Notifications page
Route::livewire('/notifications', \App\Livewire\Pages\Notifications::class)
    ->middleware(['auth', 'verified'])
    ->name('notifications');

// Contact us page
Route::livewire('/contact', 'contact-us')
    ->middleware(['auth', 'verified'])
    ->name('contact');

// Direktórium PDF page serving (auth-protected – copyright)
Route::get('/direktorium/{edition}/page/{page}', function (\App\Models\DirektoriumEdition $edition, int $page) {
    abort_if(! \Illuminate\Support\Facades\Storage::disk('private')->exists($edition->file_path), 404);
    abort_if($edition->total_pages && ($page < 1 || $page > $edition->total_pages), 404);

    $fullPath = \Illuminate\Support\Facades\Storage::disk('private')->path($edition->file_path);

    return response()->file($fullPath, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline',
    ]);
})->middleware(['auth', 'verified'])->name('direktorium.page');
