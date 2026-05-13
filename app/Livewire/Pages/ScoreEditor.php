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

    /** @var array<string, array<string, array<string, mixed>>> */
    public array $settings = [];

    public function mount(mixed $score = null, mixed $music = null): void
    {
        if ($score instanceof Score) {
            $this->authorize('update', $score);
            $this->score = $score->load('music');
            $this->musicId = $score->music_id;
            $this->title = $score->title;
            $this->format = $score->format->value;
            $this->content = $score->content;
            $this->settings = $score->settings ?? [];

            return;
        }

        $this->authorize('create', Score::class);

        if (is_numeric($music)) {
            $music = Music::query()->find((int) $music);
        }

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

    /**
     * @param  array<string, mixed>|null  $ratioSettings
     */
    public function save(?array $ratioSettings = null, ?string $ratio = null): void
    {
        $this->authorize($this->score ? 'update' : 'create', $this->score ?? Score::class);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'format' => ['required', Rule::enum(ScoreFormat::class)],
            'content' => ['required', 'string'],
            'musicId' => ['nullable', 'integer'],
        ]);

        $musicId = $this->resolveMusicId($validated['musicId']);

        $settings = $this->settings;
        if (is_string($ratio) && $ratio !== '' && is_array($ratioSettings)) {
            $settings[$validated['format']][$ratio] = $ratioSettings;
        }

        $score = $this->score ?? new Score(['user_id' => Auth::id()]);
        $score->fill([
            'music_id' => $musicId,
            'title' => $validated['title'],
            'format' => $validated['format'],
            'content' => $validated['content'],
            'settings' => $settings ?: null,
        ]);
        $score->user_id = $score->user_id ?: Auth::id();
        $score->save();

        $this->settings = $settings;

        $this->dispatch($this->score ? 'score-updated' : 'score-created');
        $this->redirectRoute('scores.edit', ['score' => $score->id], navigate: true);
    }

    /**
     * @param  array<string, mixed>  $ratioSettings
     */
    public function saveAsDefault(array $ratioSettings, string $ratio, string $format): void
    {
        if (! ScoreFormat::tryFrom($format) instanceof ScoreFormat) {
            return;
        }

        if ($ratio === '') {
            return;
        }

        $user = Auth::user();
        abort_unless($user instanceof \App\Models\User, 403);

        $defaults = $user->score_settings ?? [];
        $defaults[$format][$ratio] = $ratioSettings;
        $user->score_settings = $defaults;
        $user->save();

        $this->dispatch('score-defaults-saved');
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
        $user = Auth::user();

        return view('livewire.pages.score-editor', [
            'formats' => ScoreFormat::cases(),
            'userDefaults' => $user instanceof \App\Models\User ? ($user->score_settings ?? []) : [],
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
