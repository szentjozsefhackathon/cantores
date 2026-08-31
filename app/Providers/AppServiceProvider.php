<?php

namespace App\Providers;

use App\Listeners\UpdateLastLoginAt;
use App\Models\Author;
use App\Models\City;
use App\Models\Collection;
use App\Models\ExternalLink;
use App\Models\FirstName;
use App\Models\Genre;
use App\Models\Music;
use App\Models\User;
use App\Observers\AuthorObserver;
use App\Observers\CityObserver;
use App\Observers\CollectionObserver;
use App\Observers\ExternalLinkObserver;
use App\Observers\FirstNameObserver;
use App\Observers\GenreObserver;
use App\Observers\MusicObserver;
use App\Observers\UserObserver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
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
        $this->app->singleton(\App\Services\SvgToPdfConverter::class, fn () => \App\Services\SvgToPdfConverter::fromConfig());
        $this->app->singleton(\App\Services\MuseScoreRenderer::class, fn () => \App\Services\MuseScoreRenderer::fromConfig());
        $this->app->singleton(\App\Services\PdfPageRasterizer::class, fn () => \App\Services\PdfPageRasterizer::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        View::addNamespace('mail', resource_path('views/mail'));

        Genre::observe(GenreObserver::class);
        City::observe(CityObserver::class);
        FirstName::observe(FirstNameObserver::class);
        Collection::observe(CollectionObserver::class);
        Music::observe(MusicObserver::class);
        Author::observe(AuthorObserver::class);
        ExternalLink::observe(ExternalLinkObserver::class);
        User::observe(UserObserver::class);

        Event::listen(Login::class, UpdateLastLoginAt::class);
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
            ? Password::min(8)
                ->uncompromised()
            : null
        );
    }
}
