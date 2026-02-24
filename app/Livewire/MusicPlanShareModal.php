<?php

namespace App\Livewire;

use App\Models\MusicPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class MusicPlanShareModal extends Component
{
    public MusicPlan $musicPlan;

    public bool $showModal = false;

    public string $shareText = '';

    public function mount(MusicPlan $musicPlan): void
    {
        // Check authorization
        if (! Gate::allows('view', $musicPlan)) {
            abort(403);
        }

        $this->musicPlan = $musicPlan;
    }

    public function openModal(): void
    {
        $this->shareText = $this->generateShareText();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function copyToClipboard(): void
    {
        $this->dispatch('copy-to-clipboard', $this->shareText);
    }

    private function generateShareText(): string
    {
        $user = Auth::user();
        $assignmentsByPivot = $this->musicPlan->musicAssignments()
            ->with(['music.collections', 'music.authors', 'scopes'])
            ->orderBy('music_plan_slot_plan_id')
            ->orderBy('music_sequence')
            ->get()
            ->groupBy('music_plan_slot_plan_id');

        $planSlots = $this->musicPlan->slots()
            ->visibleToUser($user)
            ->withPivot('id', 'sequence')
            ->orderBy('music_plan_slot_plan.sequence')
            ->get()
            ->map(function ($slot) use ($assignmentsByPivot) {
                $pivotId = $slot->pivot->id;
                $assignments = $assignmentsByPivot->get($pivotId, collect());

                return [
                    'id' => $slot->id,
                    'pivot_id' => $pivotId,
                    'name' => $slot->name,
                    'description' => $slot->description,
                    'sequence' => $slot->pivot->sequence,
                    'assignments' => $assignments->map(function ($assignment) {
                        return [
                            'id' => $assignment->id,
                            'music_id' => $assignment->music_id,
                            'music_sequence' => $assignment->music_sequence,
                            'notes' => $assignment->notes,
                            'music' => $assignment->music,
                            'scope_label' => $assignment->scope_label,
                        ];
                    })->all(),
                ];
            })
            ->values()
            ->all();

        $firstCelebration = $this->musicPlan->celebrations->first();

        $text = '🎵 '.($this->musicPlan->celebration_name ?? 'Énekrend')."\n";
        $text .= "═══════════════════════════════════════\n\n";

        // Date and liturgical info
        if ($this->musicPlan->actual_date) {
            $text .= '📅 Dátum: '.$this->musicPlan->actual_date->translatedFormat('Y. F j.')."\n";
        }

        if ($firstCelebration && $firstCelebration->year_letter) {
            $text .= '✝️ Liturgikus év: '.$firstCelebration->year_letter;
            if ($firstCelebration->year_parity) {
                $text .= ' ('.$firstCelebration->year_parity.')';
            }
            $text .= "\n";
        }

        if ($firstCelebration && $firstCelebration->season_text) {
            $text .= '🕯️ Időszak: '.$firstCelebration->season_text;
            if ($firstCelebration->week) {
                $text .= ' - '.$firstCelebration->week.'. hét';
            }
            $text .= "\n";
        }

        if ($this->musicPlan->day_name) {
            $text .= '📖 Nap: '.$this->musicPlan->day_name."\n";
        }

        $text .= "\n";

        // Slots and music
        foreach ($planSlots as $slot) {
            $text .= '▶ '.$slot['sequence'].'. '.$slot['name'];
            if ($slot['description']) {
                $text .= ' ('.$slot['description'].')';
            }
            $text .= "\n";

            if (! empty($slot['assignments'])) {
                foreach ($slot['assignments'] as $assignment) {
                    if (! empty($assignment['music'])) {
                        $text .= '  • '.$assignment['music']->title;

                        if ($assignment['music']->subtitle) {
                            $text .= ' - '.$assignment['music']->subtitle;
                        }

                        // Add collections
                        if ($assignment['music']->collections->isNotEmpty()) {
                            $collections = $assignment['music']->collections
                                ->map(function ($collection) {
                                    $abbr = $collection->abbreviation ?? substr($collection->title, 0, 8);
                                    if ($collection->pivot->order_number) {
                                        return $abbr.' '.$collection->pivot->order_number;
                                    }

                                    return $abbr;
                                })
                                ->join(', ');
                            $text .= ' ['.$collections.']';
                        }

                        // Add authors
                        if ($assignment['music']->authors->isNotEmpty()) {
                            $authors = $assignment['music']->authors
                                ->pluck('name')
                                ->join(', ');
                            $text .= ' ('.$authors.')';
                        }

                        // Add scope label
                        if (! empty($assignment['scope_label'])) {
                            $text .= ' {'.$assignment['scope_label'].'}';
                        }

                        // Add notes
                        if (! empty($assignment['notes'])) {
                            $text .= ' - '.$assignment['notes'];
                        }

                        $text .= "\n";
                    }
                }
            } else {
                $text .= "  (nincs zene)\n";
            }

            $text .= "\n";
        }

        // Footer
        $text .= "═══════════════════════════════════════\n";
        if ($this->musicPlan->user) {
            $text .= 'Készítette: '.$this->musicPlan->user->name."\n";
        }
        $text .= 'Létrehozva: '.$this->musicPlan->created_at->translatedFormat('Y. m. d.')."\n";

        return $text;
    }

    public function render()
    {
        return view('music-plan-share-modal');
    }
}
