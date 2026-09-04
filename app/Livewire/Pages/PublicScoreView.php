<?php

namespace App\Livewire\Pages;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Services\PublicScoreAccessService;
use App\Services\ScoreAttributionBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * A published score, as a guest sees it.
 *
 * Deliberately a sibling of ScoreView rather than a branch inside it: this page
 * is indexable, its files come from the publication rather than a grant, and it
 * carries an attribution block that the share page has no business showing.
 */
class PublicScoreView extends Component
{
    public ?Score $score = null;

    public string $title = '';

    public ?string $format = null;

    public string $content = '';

    /** @var array<string, array<string, array<string, mixed>>> */
    public array $settings = [];

    /**
     * Whether an editor is reading a nomination that is not live yet, rather
     * than the public reading a published score.
     */
    public bool $isPreview = false;

    public function mount(PublicScoreAccessService $access, Score $score, ?string $slug = null): void
    {
        $access->requireVisible($score);

        $this->isPreview = $access->isPreview($score);

        $canonicalSlug = $score->publicSlug();

        // One canonical URL per score, so the slug cannot fragment its ranking.
        // Thrown rather than returned through Livewire's redirect helper, which
        // cannot issue a 301 — and a 302 here would leave both URLs indexed.
        if ($slug !== $canonicalSlug) {
            throw new HttpResponseException(new RedirectResponse(
                $score->publicUrl(),
                301,
            ));
        }

        $this->score = $score->load(['urls', 'music.authors', 'music.collections', 'publication']);
        $this->title = $score->title;
        $this->format = $score->format?->value;
        $this->content = $score->content ?? '';
        $this->settings = $score->settings ?? [];
    }

    #[Computed]
    public function publication(): ScorePublication
    {
        return $this->score->publication;
    }

    /**
     * The files this publication offers, oldest first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoreFile>
     */
    #[Computed]
    public function scoreFiles(): Collection
    {
        return $this->score?->publishedFiles() ?? new Collection;
    }

    #[Computed]
    public function filesRendering(): bool
    {
        return $this->scoreFiles->contains(fn (ScoreFile $scoreFile): bool => $scoreFile->isRendering());
    }

    /**
     * URLs of every rendered page, in page order, keyed by score file id.
     *
     * @return array<int, list<string>>
     */
    #[Computed]
    public function filePageUrls(): array
    {
        $urls = [];

        foreach ($this->scoreFiles as $scoreFile) {
            $urls[$scoreFile->id] = array_map(
                fn (int $page): string => route('public-scores.file.page', [
                    'score' => $this->score,
                    'scoreFile' => $scoreFile,
                    'page' => $page,
                ]),
                $scoreFile->pageNumbers(),
            );
        }

        return $urls;
    }

    #[Computed]
    public function attribution(): string
    {
        return app(ScoreAttributionBuilder::class)->line($this->publication);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function structuredData(): array
    {
        return app(ScoreAttributionBuilder::class)->structuredData($this->publication);
    }

    public function rendering(IlluminateView $view): void
    {
        if (! $this->score instanceof Score) {
            return;
        }

        $license = $this->publication->effectiveLicense();

        $view->layout('layouts::app.main', [
            'title' => $this->score->title,
            'description' => __('Free downloadable sheet music: :title (:license)', [
                'title' => $this->score->title,
                'license' => $license->shortCode(),
            ]),
            'canonical' => $this->score->publicUrl(),
            // A nomination is not the library's page yet, and must not be
            // indexed as if it were.
            'noindex' => $this->isPreview,
            'ogImage' => $this->ogImage(),
            // No licence metadata for a score nobody has approved yet.
            'jsonLd' => $this->isPreview ? null : $this->structuredData,
        ]);
    }

    /**
     * The first page of music, so a shared link previews actual notation.
     */
    private function ogImage(): ?string
    {
        $file = $this->scoreFiles->first(fn (ScoreFile $scoreFile): bool => $scoreFile->isReady());

        if ($file instanceof ScoreFile) {
            return route('public-scores.file.page', [
                'score' => $this->score,
                'scoreFile' => $file,
                'page' => 1,
            ]);
        }

        return $this->score->hasIncipit() ? $this->score->publicIncipitUrl() : null;
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.public-score-view');
    }
}
