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

Route::livewire('/music-plan/{musicPlan}', 'pages::music-plan.music-plan-editor')
    ->middleware(['auth', 'verified'])
    ->name('music-plan-editor');
