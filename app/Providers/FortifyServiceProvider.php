<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::registerView(function () {
            $cities = \App\Models\City::orderBy('name')->get();
            $firstNames = \App\Models\FirstName::orderBy('name')->get();

            // Get used combinations
            $usedCombinations = \App\Models\User::select('city_id', 'first_name_id')
                ->get()
                ->map(fn ($user) => $user->city_id.'_'.$user->first_name_id)
                ->toArray();

            // Find a random available combination
            $selectedCityId = null;
            $selectedFirstNameId = null;
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

            return view('pages::auth.register', [
                'cities' => $cities,
                'firstNames' => $firstNames,
                'selectedCityId' => old('city_id', $selectedCityId),
                'selectedFirstNameId' => old('first_name_id', $selectedFirstNameId),
            ]);
        });
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
