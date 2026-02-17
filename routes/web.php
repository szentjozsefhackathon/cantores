<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('/about', 'pages.about')->name('about');

Route::get('/random-nickname', function () {
    $cities = \App\Models\City::orderBy('name')->get();
    $firstNames = \App\Models\FirstName::orderBy('name')->get();

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

// Music Library - Editor routes (accessible to all authenticated users)
Route::livewire('/collections', \App\Livewire\Pages\Editor\Collections::class)
    ->middleware(['auth', 'verified'])
    ->name('collections');

// Public read-only collection view
Route::livewire('/collection/{collection}/view', \App\Livewire\Pages\CollectionView::class)
    ->name('collection-view');

Route::livewire('/authors', \App\Livewire\Pages\Editor\Authors::class)
    ->middleware(['auth', 'verified'])
    ->name('authors');

// Public read-only author view
Route::livewire('/author/{author}/view', \App\Livewire\Pages\AuthorView::class)
    ->name('author-view');

Route::livewire('/musics', \App\Livewire\Pages\Editor\Musics::class)
    ->middleware(['auth', 'verified'])
    ->name('musics');

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

// Music verification tool
Route::livewire('/editor/musics/verify', \App\Livewire\Pages\Editor\MusicVerifier::class)
    ->middleware(['auth', 'verified'])
    ->name('music-verifier');

// Suggestions page for music plan recommendations
Route::livewire('/suggestions', 'pages::suggestions')
    ->name('suggestions');

// Notifications page
Route::livewire('/notifications', \App\Livewire\Pages\Notifications::class)
    ->middleware(['auth', 'verified'])
    ->name('notifications');
