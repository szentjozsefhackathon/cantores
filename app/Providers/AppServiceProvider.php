<?php

namespace App\Providers;

use App\Models\Author;
use App\Models\City;
use App\Models\Collection;
use App\Models\FirstName;
use App\Models\Genre;
use App\Models\Music;
use App\Models\User;
use App\Observers\AuthorObserver;
use App\Observers\CityObserver;
use App\Observers\CollectionObserver;
use App\Observers\FirstNameObserver;
use App\Observers\GenreObserver;
use App\Observers\MusicObserver;
use App\Observers\UserObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\GenreContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Genre::observe(GenreObserver::class);
        City::observe(CityObserver::class);
        FirstName::observe(FirstNameObserver::class);
        Collection::observe(CollectionObserver::class);
        Music::observe(MusicObserver::class);
        Author::observe(AuthorObserver::class);
        User::observe(UserObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
