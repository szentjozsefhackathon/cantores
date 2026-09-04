<?php

namespace App\Livewire;

use App\Enums\ScoreRightsClaimantCapacity;
use App\Models\Score;
use App\Services\ScoreRightsReportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

/**
 * Reporting a rights problem from the score's own page.
 *
 * Deliberately open to guests: the people most likely to object to a published
 * score — a composer, a publisher, an heir — have no account here, and asking
 * them to register first would make the complaint harder to file than the
 * publication was to make.
 */
class ScoreRightsReportModal extends Component
{
    public Score $score;

    public bool $showModal = false;

    public string $capacity = '';

    public string $claim = '';

    public string $reporterName = '';

    public string $reporterEmail = '';

    /**
     * The filed report's id, kept so the reporter can quote it if they write in
     * again. Set only after a successful submission.
     */
    public ?int $filedReportId = null;

    public function mount(Score $score): void
    {
        $this->score = $score;
        $this->prefillFromAccount();
    }

    public function openModal(): void
    {
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset('capacity', 'claim', 'filedReportId');
        $this->resetValidation();
        $this->prefillFromAccount();
    }

    public function submit(ScoreRightsReportService $reports): void
    {
        $validated = $this->validate([
            'capacity' => ['required', 'string', 'in:'.implode(',', array_column(ScoreRightsClaimantCapacity::cases(), 'value'))],
            'claim' => ['required', 'string', 'min:20', 'max:2000'],
            'reporterName' => ['required', 'string', 'max:120'],
            'reporterEmail' => ['required', 'email', 'max:180'],
        ], [
            'capacity.required' => __('Tell us in what capacity you are writing.'),
            'claim.required' => __('Describe what is wrong with this score.'),
            'claim.min' => __('Please say a little more, so an editor can act on it.'),
            'reporterEmail.required' => __('Leave an address we can answer on.'),
        ]);

        if ($this->isRateLimited()) {
            return;
        }

        $report = $reports->file($this->score, [
            'capacity' => ScoreRightsClaimantCapacity::from($validated['capacity']),
            'claim' => $validated['claim'],
            'reporter_name' => $validated['reporterName'],
            'reporter_email' => $validated['reporterEmail'],
        ], Auth::user());

        RateLimiter::hit($this->rateLimiterKey(), 3600);

        $this->filedReportId = $report->id;
        $this->reset('claim');
    }

    /**
     * @return list<\App\Enums\ScoreRightsClaimantCapacity>
     */
    public function capacities(): array
    {
        return ScoreRightsClaimantCapacity::cases();
    }

    /**
     * Guard against a flood of reports from one place. Deliberately generous:
     * a genuine rights holder may object to several scores in one sitting.
     */
    private function isRateLimited(): bool
    {
        if (RateLimiter::tooManyAttempts($this->rateLimiterKey(), 10)) {
            $this->addError('claim', __('You have sent us several reports already. Please give us time to read them.'));

            return true;
        }

        return false;
    }

    private function rateLimiterKey(): string
    {
        return 'score-rights-report:'.(Auth::id() ?? request()->ip());
    }

    private function prefillFromAccount(): void
    {
        $user = Auth::user();

        $this->reporterName = $user?->display_name ?: ($user?->name ?? '');
        $this->reporterEmail = $user?->email ?? '';
    }

    public function render(): IlluminateView
    {
        return view('livewire.score-rights-report-modal');
    }
}
