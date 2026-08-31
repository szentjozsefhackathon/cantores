<?php

namespace App\Livewire\Pages;

use App\Models\MusicPlan;
use App\Models\Score;
use App\Models\ScoreUrl;
use App\Models\Share;
use App\Services\ShareAccessService;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

class MusicPlanShareView extends Component
{
    public ?MusicPlan $musicPlan = null;

    /** @var array<int, array<string, mixed>> */
    public array $planSlots = [];

    public string $shareToken = '';

    public function mount(string $token): void
    {
        $share = app(ShareAccessService::class)->resolveOfType($token, MusicPlan::class);
        abort_if(! $share instanceof Share, 404);

        $share->touchLastViewed();

        /** @var MusicPlan $musicPlan */
        $musicPlan = $share->shareable;

        $this->shareToken = $token;
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
        $scoresByMusicId = $this->musicPlan->reachableScores()
            ->with('urls')
            ->get()
            ->groupBy('music_id');

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
                                'share_url' => $s->shareUrl($this->shareToken),
                                'incipit_url' => $s->hasIncipit()
                                    ? $s->shareIncipitUrl($this->shareToken)
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
