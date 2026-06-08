<?php

namespace App\Livewire\Pages;

use App\Models\Score;
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

    public function mount(string $token): void
    {
        $score = Score::query()->where('share_token', $token)->first();
        abort_if($score === null, 404);

        if (Auth::check() && Auth::id() === $score->user_id) {
            $this->redirectRoute('scores.edit', ['score' => $score->id], navigate: true);

            return;
        }

        $this->score = $score->load('urls');
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
