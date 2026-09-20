<?php

namespace App\Providers;

use App\Listeners\UpdateLastLoginAt;
use App\Models\Author;
use App\Models\City;
use App\Models\Collection;
use App\Models\DeviceName;
use App\Models\DevicePairing;
use App\Models\ExternalLink;
use App\Models\FirstName;
use App\Models\Genre;
use App\Models\Music;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\ProjectionMusic;
use App\Models\ProjectionSlide;
use App\Models\Screen;
use App\Models\User;
use App\Observers\AuthorObserver;
use App\Observers\CityObserver;
use App\Observers\CollectionObserver;
use App\Observers\ExternalLinkObserver;
use App\Observers\FirstNameObserver;
use App\Observers\GenreObserver;
use App\Observers\MusicObserver;
use App\Observers\ProjectionRevisionObserver;
use App\Observers\ShowStreamObserver;
use App\Observers\UserObserver;
use App\Services\GenreContext;
use App\Services\MuseScoreRenderer;
use App\Services\PdfPageRasterizer;
use App\Services\PdfPageVectorizer;
use App\Services\SvgToPdfConverter;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        // `scoped` rather than `singleton`, and the difference only exists under
        // Octane: a worker serves many requests from one container, and a
        // singleton resolved during the first of them is handed to every request
        // after it. GenreContext happens to be safe either way — it asks
        // `Auth::user()` and the session afresh on every call rather than
        // resolving them once and holding them — but "safe because of how it is
        // written today" is not a property a binding should depend on. Scoped
        // makes the container throw it away between requests, so the next person
        // to add a memoised field here cannot leak one cantor's genre into
        // another cantor's page.
        $this->app->scoped(GenreContext::class);

        // These four stay singletons deliberately. Each is built from config and
        // holds a binary path and a timeout — nothing derived from a request, a
        // user or a session — so there is nothing for a worker to leak, and
        // rebuilding them per request would only be work.
        $this->app->singleton(SvgToPdfConverter::class, fn () => SvgToPdfConverter::fromConfig());
        $this->app->singleton(MuseScoreRenderer::class, fn () => MuseScoreRenderer::fromConfig());
        $this->app->singleton(PdfPageRasterizer::class, fn () => PdfPageRasterizer::fromConfig());
        $this->app->singleton(PdfPageVectorizer::class, fn () => PdfPageVectorizer::fromConfig());
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
        Projection::observe(ProjectionRevisionObserver::class);
        ProjectionSlide::observe(ProjectionRevisionObserver::class);
        ProjectionMusic::observe(ProjectionRevisionObserver::class);

        foreach ([Presentation::class, Screen::class, DeviceName::class, DevicePairing::class, Projection::class, ProjectionSlide::class, ProjectionMusic::class] as $model) {
            $model::observe(ShowStreamObserver::class);
        }

        Event::listen(Login::class, UpdateLastLoginAt::class);

        $this->configureProjectionLimits();
    }

    /**
     * A ceiling on the two endpoints a running service leans on.
     *
     * Not a defence against anybody: both routes are behind `auth`, and the
     * numbers are far above what a cantor with a wall and a phone comes to. It
     * is a ceiling on *us* — a client that has lost its place and is asking in a
     * loop, a tab left open from last Sunday multiplied by every parish — so
     * that one browser's mistake cannot become the whole site's afternoon.
     *
     * Named rather than `throttle:300,1`, and that is not a style choice: the
     * inline form keys on the user alone with no prefix, so every inline
     * throttle in the application shares one bucket per person. A cantor polling
     * at this rate would have spent the PDF export's twenty attempts within four
     * seconds of starting a Mass. A named limiter folds its own name into the
     * key and cannot collide.
     */
    protected function configureProjectionLimits(): void
    {
        // The wall reads the screen once a second and reports every ten; the
        // phone reads it once a second beside it. That is a little over 120 a
        // minute for one service, and this leaves room for a second window,
        // a reload, and a deck being swapped, while still being a hundredth of
        // what a runaway loop asks for.
        RateLimiter::for('projection-poll', fn (Request $request): Limit => Limit::perMinute(300)
            ->by($this->projectionLimitKey($request)));

        // The deck itself, which is read when an edit lands and not otherwise.
        // Its own bucket, so that a storm of re-engravings during a rehearsal
        // cannot spend the budget the polling needs to keep a Mass following.
        RateLimiter::for('projection-payload', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($this->projectionLimitKey($request)));

        RateLimiter::for('projection-resync', fn (Request $request): Limit => Limit::perMinute(6)
            ->by($this->projectionLimitKey($request).':'.(string) $request->route('presentation')));
    }

    /**
     * Who is asking — the person where there is one, and the address otherwise.
     */
    protected function projectionLimitKey(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
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
