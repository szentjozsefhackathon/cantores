<?php

namespace App\Livewire\Pages;

use App\Enums\ScoreFormat;
use App\Models\Music;
use App\Models\Score;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ScoreEditor extends Component
{
    use AuthorizesRequests;

    public ?Score $score = null;

    public ?int $musicId = null;

    public string $title = '';

    public string $format = 'abc';

    public string $content = '';

    public function mount(mixed $score = null, mixed $music = null): void
    {
        if ($score instanceof Score) {
            $this->authorize('update', $score);
            $this->score = $score->load('music');
            $this->musicId = $score->music_id;
            $this->title = $score->title;
            $this->format = $score->format->value;
            $this->content = $score->content;

            return;
        }

        $this->authorize('create', Score::class);

        if ($music instanceof Music) {
            abort_unless(Gate::allows('view', $music), 403);

            $this->musicId = $music->id;
            $this->title = $music->title;
        }
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', [
            'title' => $this->score ? __('Edit Score') : __('Create Score'),
        ]);
    }

    public function save(): void
    {
        $this->authorize($this->score ? 'update' : 'create', $this->score ?? Score::class);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'format' => ['required', Rule::enum(ScoreFormat::class)],
            'content' => ['required', 'string'],
            'musicId' => ['nullable', 'integer'],
        ]);

        $musicId = $this->resolveMusicId($validated['musicId']);

        $score = $this->score ?? new Score(['user_id' => Auth::id()]);
        $score->fill([
            'music_id' => $musicId,
            'title' => $validated['title'],
            'format' => $validated['format'],
            'content' => $validated['content'],
        ]);
        $score->user_id = $score->user_id ?: Auth::id();
        $score->save();

        $this->dispatch($this->score ? 'score-updated' : 'score-created');
        $this->redirectRoute('scores.edit', ['score' => $score->id], navigate: true);
    }

    public function delete(): void
    {
        abort_unless($this->score instanceof Score, 404);

        $this->authorize('delete', $this->score);
        $this->score->delete();

        $this->redirectRoute('scores', navigate: true);
    }

    #[Computed]
    public function selectedMusic(): ?Music
    {
        if ($this->musicId === null) {
            return null;
        }

        return Music::query()->find($this->musicId);
    }

    #[On('music-selected.score')]
    public function onMusicSelected(int $musicId): void
    {
        $music = Music::query()->findOrFail($musicId);
        abort_unless(Gate::allows('view', $music), 403);

        $this->musicId = $music->id;
        $this->title = $music->title;
        $this->js("Flux.modal('score-music-search').close()");
    }

    public function clearMusic(): void
    {
        $this->musicId = null;
    }

    public function render()
    {
        return view('livewire.pages.score-editor', [
            'formats' => ScoreFormat::cases(),
        ]);
    }

    private function resolveMusicId(?int $musicId): ?int
    {
        if ($musicId === null) {
            return null;
        }

        $music = Music::query()->findOrFail($musicId);
        abort_unless(Gate::allows('view', $music), 403);

        return $music->id;
    }
}
