<?php

namespace App\Livewire\Pages;

use App\Models\Loan;
use App\Models\MusicPlan;
use App\Models\Score;
use App\Models\ScoreUrl;
use App\Services\LoanAccessService;
use App\Services\LoanKeepingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

class MusicPlanLoanView extends Component
{
    public ?MusicPlan $musicPlan = null;

    /** @var array<int, array<string, mixed>> */
    public array $planSlots = [];

    public string $loanToken = '';

    /** Whose plan this is, so a borrowed page says whose work it is. */
    public string $ownerName = '';

    public bool $kept = false;

    public bool $canKeep = false;

    public function mount(string $token): void
    {
        $loan = app(LoanAccessService::class)->resolveOfType($token, MusicPlan::class);
        abort_if(! $loan instanceof Loan, 404);

        $loan->touchLastViewed();

        $receipt = app(LoanKeepingService::class)->recordOpen($loan, Auth::user());

        /** @var MusicPlan $musicPlan */
        $musicPlan = $loan->lendable;

        $this->loanToken = $token;
        $this->musicPlan = $musicPlan->load(['celebration', 'user', 'genre']);
        $this->ownerName = $musicPlan->user?->displayName ?? '';
        $this->canKeep = Auth::check() && Auth::id() !== $musicPlan->user_id;
        $this->kept = $receipt?->isKept() === true;
        $this->loadPlanSlots($loan);
    }

    /**
     * Save the plan — its arrangement, musics and order — into the reader's own
     * lending centre.
     *
     * This one the lender can take back: revoking a plan loan takes back the plan.
     * Scores inside it that belong to someone else are kept separately, against the
     * loan they originate from, from the score's own page.
     */
    public function keep(): void
    {
        if (! Auth::check()) {
            return;
        }

        $loan = app(LoanAccessService::class)->resolveOfType($this->loanToken, MusicPlan::class);

        if (! $loan instanceof Loan) {
            return;
        }

        if (app(LoanKeepingService::class)->keep($loan, Auth::user()) === null) {
            return;
        }

        $this->kept = true;

        $this->dispatch('toast', message: __('Saved to your loans.'), type: 'success');
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
        return view('livewire.pages.music-plan-loan-view');
    }

    /**
     * The scores each music in the plan opens for this reader.
     *
     * Resolved through the loan rather than off the plan, so the lender's exclusions
     * apply and any score they borrowed and are passing on travels with the link.
     */
    private function loadPlanSlots(Loan $loan): void
    {
        $scoresByMusicId = app(LoanAccessService::class)
            ->scoresFor($loan)
            ->load(['urls', 'user'])
            ->groupBy('music_id');

        $lenderId = $loan->user_id;

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
            ->map(function ($slot) use ($assignmentsByPivot, $scoresByMusicId, $lenderId) {
                $pivotId = $slot->pivot->id;
                $assignments = $assignmentsByPivot->get($pivotId, collect());

                return [
                    'id' => $slot->id,
                    'pivot_id' => $pivotId,
                    'name' => $slot->name,
                    'description' => $slot->description,
                    'sequence' => $slot->pivot->sequence,
                    'assignments' => $assignments->map(function ($assignment) use ($scoresByMusicId, $lenderId) {
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
                                // Attribution is what lending buys over re-uploading,
                                // so a score the lender borrowed says whose it is.
                                'owner_name' => $s->user?->displayName,
                                'is_passed_on' => $s->user_id !== $lenderId,
                                'format' => $s->format?->label() ?? __('Links'),
                                'format_value' => $s->format?->value,
                                'loan_url' => $s->loanUrl($this->loanToken),
                                'incipit_url' => $s->hasIncipit()
                                    ? $s->loanIncipitUrl($this->loanToken)
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
