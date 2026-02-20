<?php

namespace App\Livewire\Pages\Admin;

use App\Models\WhitelistRule;
use App\Services\UrlWhitelistValidator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class UrlWhitelistManager extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public ?int $editingId = null;

    public array $form = [
        'hostname' => '',
        'path_prefix' => '',
        'scheme' => 'https',
        'allow_any_port' => false,
        'description' => '',
        'is_active' => true,
    ];

    public string $search = '';

    public string $statusFilter = '';

    public array $selectedRules = [];

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public bool $showCreateModal = false;

    public string $testUrl = '';

    public array $testResult = [];

    public function mount(): void
    {
        $this->authorize('system.maintain');
    }

    public function rules(): array
    {
        return [
            'form.hostname' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.-]+$/'],
            'form.path_prefix' => ['nullable', 'string', 'max:255', 'regex:/^\/[a-zA-Z0-9\-._~%!$&\'()*+,;=:@\/]*$/'],
            'form.scheme' => ['required', 'in:http,https'],
            'form.allow_any_port' => ['boolean'],
            'form.description' => ['nullable', 'string', 'max:500'],
            'form.is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'form.hostname.regex' => 'Hostname can only contain letters, numbers, dots, and hyphens.',
            'form.path_prefix.regex' => 'Path prefix must start with / and contain valid URL characters.',
        ];
    }

    public function getRulesProperty()
    {
        return WhitelistRule::query()
            ->when($this->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('hostname', 'ilike', "%{$search}%")
                        ->orWhere('path_prefix', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('hostname')
            ->orderBy('path_prefix')
            ->paginate(20);
    }

    public function create(): void
    {
        $this->authorize('system.maintain');
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('system.maintain');
        $rule = WhitelistRule::findOrFail($id);
        $this->editingId = $id;
        $this->form = [
            'hostname' => $rule->hostname,
            'path_prefix' => $rule->path_prefix,
            'scheme' => $rule->scheme,
            'allow_any_port' => $rule->allow_any_port,
            'description' => $rule->description,
            'is_active' => $rule->is_active,
        ];
        $this->showCreateModal = true;
    }

    public function save(): void
    {
        $this->authorize('system.maintain');
        $validated = $this->validate();

        // Check for uniqueness
        $existing = WhitelistRule::query()
            ->where('hostname', $validated['form']['hostname'])
            ->where('path_prefix', $validated['form']['path_prefix'])
            ->where('scheme', $validated['form']['scheme'])
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();

        if ($existing) {
            $this->addError('form.hostname', 'A rule with this hostname, path prefix, and scheme combination already exists.');

            return;
        }

        if ($this->editingId) {
            $rule = WhitelistRule::findOrFail($this->editingId);
            $rule->update($validated['form']);
            $message = 'Rule updated successfully.';
        } else {
            WhitelistRule::create($validated['form']);
            $message = 'Rule created successfully.';
        }

        $this->resetForm();
        $this->showCreateModal = false;
        session()->flash('message', $message);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('system.maintain');
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $this->authorize('system.maintain');
        $rule = WhitelistRule::findOrFail($this->deletingId);
        $rule->delete();
        $this->showDeleteModal = false;
        $this->selectedRules = array_diff($this->selectedRules, [$this->deletingId]);
        session()->flash('message', 'Rule deleted successfully.');
    }

    public function bulkActivate(): void
    {
        $this->authorize('system.maintain');
        WhitelistRule::whereIn('id', $this->selectedRules)->update(['is_active' => true]);
        $this->selectedRules = [];
        session()->flash('message', 'Selected rules activated.');
    }

    public function bulkDeactivate(): void
    {
        $this->authorize('system.maintain');
        WhitelistRule::whereIn('id', $this->selectedRules)->update(['is_active' => false]);
        $this->selectedRules = [];
        session()->flash('message', 'Selected rules deactivated.');
    }

    public function bulkDelete(): void
    {
        $this->authorize('system.maintain');
        WhitelistRule::whereIn('id', $this->selectedRules)->delete();
        $this->selectedRules = [];
        session()->flash('message', 'Selected rules deleted.');
    }

    public function testRule(): void
    {
        $this->validate([
            'testUrl' => ['required', 'url'],
        ]);

        try {
            $validator = app(UrlWhitelistValidator::class);
            $matches = $validator->validate($this->testUrl);

            $this->testResult = [
                'matches' => $matches,
                'message' => $matches ? 'URL matches whitelist rules.' : 'URL does NOT match any whitelist rule.',
            ];
        } catch (\InvalidArgumentException $e) {
            $this->testResult = [
                'matches' => false,
                'message' => 'Invalid URL: '.$e->getMessage(),
            ];
        }
    }

    public function resetForm(): void
    {
        $this->form = [
            'hostname' => '',
            'path_prefix' => '',
            'scheme' => 'https',
            'allow_any_port' => false,
            'description' => '',
            'is_active' => true,
        ];
        $this->editingId = null;
        $this->resetErrorBag();
    }

    public function getMatchingUrlsCountProperty(): array
    {
        $counts = [];
        foreach ($this->rules as $rule) {
            $counts[$rule->id] = \App\Models\MusicUrl::where('url', 'ilike', $rule->scheme.'://'.$rule->hostname.'%')->count();
        }

        return $counts;
    }

    public function render()
    {
        return view('livewire.pages.admin.url-whitelist-manager');
    }
}
