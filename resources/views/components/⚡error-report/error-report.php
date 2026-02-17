<?php

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Model $resource = null;

    public bool $showModal = false;

    public string $message = '';

    public function mount($resource = null): void
    {
        $this->resource = $resource;
    }

    #[On('openErrorReportModal')]
    public function openModal($resourceId, $resourceType): void
    {
        // If resource is not set via mount, we can try to load from params
        if (isset($resourceId) && isset($resourceType)) {
            $modelClass = $this->getModelClass($resourceType);
            if ($modelClass) {
                $this->resource = $modelClass::find($resourceId);
            }
        }

        if (! $this->resource) {
            $this->dispatch('error', message: __('Unable to load resource for error reporting.'));

            return;
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset('message');
        // Keep resource as is for next open
    }

    public function submit(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:160'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            $this->dispatch('error-report-failed', message: __('You must be logged in to report an error.'));

            return;
        }

        if (! $this->resource) {
            $this->dispatch('error-report-failed', message: __('No resource selected.'));

            return;
        }

        /** @var NotificationService $notificationService */
        $notificationService = app(NotificationService::class);
        $notificationService->createErrorReport($user, $this->resource, $this->message);

        $this->dispatch('error-report-success', message: __('Error report submitted successfully.'));
        $this->closeModal();
    }

    private function getModelClass(string $type): ?string
    {
        return match ($type) {
            'music' => \App\Models\Music::class,
            'collection' => \App\Models\Collection::class,
            'author' => \App\Models\Author::class,
            default => null,
        };
    }

    public function render(): View
    {
        return view('components.⚡error-report.error-report');
    }
};
