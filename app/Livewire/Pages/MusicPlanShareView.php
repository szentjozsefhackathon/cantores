<?php

namespace App\Livewire\Pages;

use App\Models\MusicPlan;
use App\Models\Score;
use App\Models\ScoreUrl;
use Illuminate\Support\Str;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

class MusicPlanShareView extends Component
{
    public ?MusicPlan $musicPlan = null;

    /** @var array<int, array<string, mixed>> */
    public array $planSlots = [];

    public function mount(string $token): void
    {
        $musicPlan = MusicPlan::query()->where('share_token', $token)->first();
        abort_if($musicPlan === null, 404);

        $this->musicPlan = $musicPlan->load(['celebration', 'user', 'genre']);
        $this->loadPlanSlots();
    }

    public function rendering(IlluminateView $view): void
    {
        if (! $this->musicPlan instanceof MusicPlan) {
            return;
        }

        $celebration = $this->musicPlan->celebration_name;
        $date = $this->musicPlan->actual_date?->translatedFormat('Y. F j.');

        $title = $celebration ?? 'Énekrend';
        if ($date) {
            $title .= ' – '.$date;
        }

        $view->layout('layouts::app.main', [
            'title' => $title,
            'noindex' => true,
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.music-plan-share-view');
    }

    private function loadPlanSlots(): void
    {
        $ownerId = $this->musicPlan->user_id;

        $musicIds = $this->musicPlan->musicAssignments()
            ->pluck('music_id')
            ->unique()
            ->filter()
            ->all();

        $scores = Score::query()
            ->where('user_id', $ownerId)
            ->whereIn('music_id', $musicIds)
            ->with('urls')
            ->get();

        $scores->each(function (Score $score): void {
            if ($score->share_token !== null) {
                return;
            }

            do {
                $token = Str::random(32);
            } while (Score::query()->where('share_token', $token)->exists());

            $score->share_token = $token;
            $score->save();
        });

        $scoresByMusicId = $scores->groupBy('music_id');

        $assignmentsByPivot = $this->musicPlan->musicAssignments()
            ->with(['music.collections', 'music.authors', 'scopes'])
            ->orderBy('music_plan_slot_plan_id')
            ->orderBy('music_sequence')
            ->get()
            ->groupBy('music_plan_slot_plan_id');

        $this->planSlots = $this->musicPlan->slots()
            ->withPivot('id', 'sequence')
            ->orderBy('music_plan_slot_plan.sequence')
            ->get()
            ->map(function ($slot) use ($assignmentsByPivot, $scoresByMusicId) {
                $pivotId = $slot->pivot->id;
                $assignments = $assignmentsByPivot->get($pivotId, collect());

                return [
                    'id' => $slot->id,
                    'pivot_id' => $pivotId,
                    'name' => $slot->name,
                    'description' => $slot->description,
                    'sequence' => $slot->pivot->sequence,
                    'assignments' => $assignments->map(function ($assignment) use ($scoresByMusicId) {
                        $scores = $scoresByMusicId->get($assignment->music_id, collect());

                        return [
                            'id' => $assignment->id,
                            'music_id' => $assignment->music_id,
                            'music_sequence' => $assignment->music_sequence,
                            'notes' => $assignment->notes,
                            'music' => $assignment->music,
                            'scope_label' => $assignment->scope_label,
                            'scores' => $scores->map(fn (Score $s) => [
                                'id' => $s->id,
                                'title' => $s->title,
                                'format' => $s->format?->label() ?? __('Links'),
                                'format_value' => $s->format?->value,
                                'share_url' => $s->share_token
                                    ? route('score.share', ['token' => $s->share_token])
                                    : null,
                                'incipit_url' => ($s->share_token && $s->hasIncipit())
                                    ? route('score.share.incipit', ['token' => $s->share_token])
                                    : null,
                                'urls' => $s->urls->map(fn (ScoreUrl $url) => [
                                    'url' => $url->url,
                                    'label' => $url->label?->label() ?? $url->url,
                                    'icon' => $url->label?->icon() ?? 'link',
                                    'color' => $url->label?->color() ?? 'text-gray-500',
                                    'host' => preg_replace('/^www\./', '', parse_url($url->url, PHP_URL_HOST) ?? $url->url),
                                    'comment' => $url->comment,
                                ])->all(),
                            ])->all(),
                        ];
                    })->all(),
                ];
            })
            ->values()
            ->all();
    }
}
