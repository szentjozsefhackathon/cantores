<?php

namespace App\Livewire\Pages;

use App\Models\Score;
use App\Models\Share;
use App\Services\ShareAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

class ScoreView extends Component
{
    public ?Score $score = null;

    public string $title = '';

    public ?string $format = null;

    public string $content = '';

    /** @var array<string, array<string, array<string, mixed>>> */
    public array $settings = [];

    /**
     * The token of the grant this score is being viewed through, so the page can
     * build further links (incipits) that stay inside the same grant.
     */
    public string $shareToken = '';

    /**
     * Serves both the direct score link (/s/{token}) and a score reached through a
     * folder or plan grant (/share/{token}/score/{score}).
     */
    public function mount(string $token, mixed $score = null): void
    {
        $shareAccess = app(ShareAccessService::class);

        $share = $shareAccess->resolve($token);
        abort_if(! $share instanceof Share, 404);

        if (is_numeric($score)) {
            $score = Score::query()->find((int) $score);
        }

        if ($score instanceof Score) {
            abort_unless($shareAccess->grantsScore($share, $score), 404);
        } else {
            abort_unless($share->shareable instanceof Score, 404);
            $score = $share->shareable;
        }

        if (Auth::check() && Auth::id() === $score->user_id) {
            $this->redirectRoute('scores.edit', ['score' => $score->id], navigate: true);

            return;
        }

        $share->touchLastViewed();

        $this->score = $score->load('urls');
        $this->shareToken = $token;
        $this->title = $score->title;
        $this->format = $score->format?->value;
        $this->content = $score->content ?? '';
        $this->settings = $score->settings ?? [];
    }

    public function rendering(IlluminateView $view): void
    {
        if (! $this->score instanceof Score) {
            return;
        }

        $view->layout('layouts::app.main', [
            'title' => $this->score->title,
            'noindex' => true,
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.score-view');
    }
}
