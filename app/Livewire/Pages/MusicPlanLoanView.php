<?php

namespace App\Livewire\Pages;

use App\Models\Loan;
use App\Models\MusicPlan;
use App\Services\LoanAccessService;
use App\Services\LoanKeepingService;
use App\Services\MusicPlanScoreListService;
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
     * The plan as this reader sees it: for each music, every score they may
     * actually open.
     *
     * The service list, with the lending link as a fourth axis beside the
     * reader's own scores, the ones they kept and the public library. So a
     * flutist who has written her own setting of a music in the plan finds it
     * here, beside the part she was lent, on the link she was given — and nobody
     * else sees it. Composing is MusicPlanScoreListService's job, which is why
     * the loan is handed to it rather than resolved here: the entries have to
     * link back through the token this reader arrived on.
     */
    private function loadPlanSlots(Loan $loan): void
    {
        $scoresByMusicId = app(MusicPlanScoreListService::class)
            ->forViewer($this->musicPlan, Auth::user(), $loan);

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
                    'assignments' => $assignments->map(fn ($assignment) => [
                        'id' => $assignment->id,
                        'music_id' => $assignment->music_id,
                        'music_sequence' => $assignment->music_sequence,
                        'notes' => $assignment->notes,
                        'music' => $assignment->music,
                        'scope_label' => $assignment->scope_label,
                        'scores' => $scoresByMusicId->get($assignment->music_id, collect())->all(),
                    ])->all(),
                ];
            })
            ->values()
            ->all();
    }
}
