<?php

use App\Models\Collection;
use App\Models\Music;
use App\Models\Realm;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    use AuthorizesRequests;

    public Music $music;

    // Form fields
    public string $title = '';

    public ?string $subtitle = null;

    public ?string $customId = null;

    // Collection assignment
    public ?int $selectedCollectionId = null;

    public ?int $pageNumber = null;

    public ?string $orderNumber = null;

    // Realm assignment
    public array $selectedRealms = [];

    // Audit log
    public bool $showAuditModal = false;

    public $audits = [];

    // Edit collection modal
    public bool $showEditModal = false;
    public ?int $editingCollectionId = null;
    public ?int $editingPageNumber = null;
    public ?string $editingOrderNumber = null;

    /**
     * Mount the component.
     */
    public function mount(Music $music): void
    {
        $this->authorize('view', $music);
        $this->music = $music->load(['collections', 'realms']);
        $this->title = $music->title;
        $this->subtitle = $music->subtitle;
        $this->customId = $music->custom_id;
        $this->selectedRealms = $music->realms->pluck('id')->toArray();
    }

    /**
     * Get all realms for selection.
     */
    public function realms(): \Illuminate\Database\Eloquent\Collection
    {
        return Realm::all();
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $collections = Collection::orderBy('title')->limit(100)->get();

        return view('pages.editor.music-editor', [
            'collections' => $collections,
        ]);
    }

    /**
     * Update the music piece.
     */
    public function update(): void
    {
        $this->authorize('update', $this->music);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'customId' => ['nullable', 'string', 'max:255'],
            'selectedRealms' => ['nullable', 'array'],
            'selectedRealms.*' => ['integer', Rule::exists('realms', 'id')],
        ]);

        $this->music->update([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'],
            'custom_id' => $validated['customId'],
        ]);

        // Sync selected realms (empty array will detach all)
        $this->music->realms()->sync($validated['selectedRealms'] ?? []);

        $this->dispatch('music-updated');
    }

    /**
     * Add a collection to the music piece.
     */
    public function addCollection(): void
    {
        $this->authorize('update', $this->music);

        $validated = $this->validate([
            'selectedCollectionId' => ['required', 'integer', 'exists:collections,id'],
            'pageNumber' => ['nullable', 'integer', 'min:1'],
            'orderNumber' => ['nullable', 'string', 'max:255'],
        ]);

        // Check if already attached
        if ($this->music->collections()->where('collection_id', $validated['selectedCollectionId'])->exists()) {
            $this->dispatch('error', __('This collection is already attached to this music piece.'));

            return;
        }

        $this->music->collections()->attach($validated['selectedCollectionId'], [
            'page_number' => $validated['pageNumber'],
            'order_number' => $validated['orderNumber'],
        ]);

        // Refresh the collections relationship
        $this->music->load('collections');

        // Reset the form fields
        $this->selectedCollectionId = null;
        $this->pageNumber = null;
        $this->orderNumber = null;

        $this->dispatch('collection-added');
    }

    /**
     * Remove a collection from the music piece.
     */
    public function removeCollection(int $collectionId): void
    {
        $this->authorize('update', $this->music);

        $this->music->collections()->detach($collectionId);
        $this->music->load('collections');

        $this->dispatch('collection-removed');
    }

    /**
     * Edit a collection's pivot data.
     */
    public function editCollection(int $collectionId): void
    {
        $this->authorize('update', $this->music);
        
        $collection = $this->music->collections()->where('collection_id', $collectionId)->first();
        
        if (!$collection) {
            return;
        }
        
        $this->editingCollectionId = $collectionId;
        $this->editingPageNumber = $collection->pivot->page_number;
        $this->editingOrderNumber = $collection->pivot->order_number;
        $this->showEditModal = true;
    }

    /**
     * Update the collection's pivot data.
     */
    public function updateCollection(): void
    {
        $this->authorize('update', $this->music);

        $validated = $this->validate([
            'editingPageNumber' => ['nullable', 'integer', 'min:1'],
            'editingOrderNumber' => ['nullable', 'string', 'max:255'],
        ]);

        $this->music->collections()->updateExistingPivot($this->editingCollectionId, [
            'page_number' => $validated['editingPageNumber'],
            'order_number' => $validated['editingOrderNumber'],
        ]);

        $this->music->load('collections');
        
        $this->showEditModal = false;
        $this->editingCollectionId = null;
        $this->editingPageNumber = null;
        $this->editingOrderNumber = null;

        $this->dispatch('collection-updated');
    }

    /**
     * Show the audit log modal.
     */
    public function showAuditLog(): void
    {
        $this->authorize('view', $this->music);
        $this->audits = $this->music->audits()
            ->with(['user.city', 'user.firstName'])
            ->latest()
            ->get();
        $this->showAuditModal = true;
    }

    /**
     * Delete the music piece.
     */
    public function delete(): void
    {
        $this->authorize('delete', $this->music);

        // Check if music has any collections or plan slots assigned
        if ($this->music->collections()->count() > 0 || $this->music->musicPlanSlotAssignments()->count() > 0) {
            $this->dispatch('error', __('Cannot delete music piece that has collections or plan slots assigned to it.'));

            return;
        }

        $this->music->delete();

        $this->redirectRoute('musics');
    }
};
?>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header with back button -->
        <div class="mb-6">
            <flux:button
                variant="ghost"
                icon="arrow-left"
                :href="route('musics')"
                tag="a"
            >
                {{ __('Back to Music List') }}
            </flux:button>
        </div>

        <!-- Music summary card -->
        <div class="mb-6">
            <livewire:music-card :music="$music" />
        </div>

        <flux:card class="p-5">
            <div class="flex items-center justify-between gap-4 mb-6">
                <div>
                    <flux:heading size="xl">{{ __('Edit Music Piece') }}</flux:heading>
                    <flux:subheading>{{ $music->title }}</flux:subheading>
                </div>
                
                <div class="flex items-center gap-2">
                    <flux:button
                        variant="ghost"
                        icon="history"
                        wire:click="showAuditLog"
                        :title="__('View Audit Log')"
                    />
                    
                    <flux:button
                        variant="ghost"
                        icon="trash"
                        wire:click="delete"
                        wire:confirm="{{ __('Are you sure you want to delete this music piece? This can only be done if no collections or plan slots are assigned to it.') }}"
                        :title="__('Delete')"
                    />
                </div>
            </div>

            <!-- Edit Form -->
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:field required>
                        <flux:label>{{ __('Title') }}</flux:label>
                        <flux:input
                            wire:model="title"
                            :placeholder="__('Enter music piece title')"
                        />
                        <flux:error name="title" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Custom ID') }}</flux:label>
                        <flux:description>{{ __('Optional unique identifier, e.g., BWV 232, KV 626') }}</flux:description>
                        <flux:input
                            wire:model="customId"
                            :placeholder="__('Enter custom ID')"
                        />
                        <flux:error name="customId" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Subtitle') }}</flux:label>
                    <flux:description>{{ __('Optional subtitle, e.g., movement, part, description') }}</flux:description>
                    <flux:input
                        wire:model="subtitle"
                        :placeholder="__('Enter subtitle')"
                    />
                    <flux:error name="subtitle" />
                </flux:field>

                <!-- Realm Selection -->
                <div class="space-y-2">
                    <flux:checkbox.group variant="cards" label="{{ __('Select which realms this music piece belongs to.') }}">
                        @foreach($this->realms() as $realm)
                            <flux:checkbox
                                variant="cards"
                                wire:model="selectedRealms"
                                value="{{ $realm->id }}"
                                :label="$realm->label()"
                                :icon="$realm->icon()"
                            />
                        @endforeach
                    </flux:checkbox.group>
                    <flux:error name="selectedRealms" />
                </div>

                <!-- Save Button -->
                <div class="flex justify-end items-center gap-4">
                    <x-action-message on="music-updated">
                        {{ __('Saved.') }}
                    </x-action-message>
                    <flux:button
                        variant="primary"
                        wire:click="update"
                        wire:loading.attr="disabled"
                    >
                        {{ __('Save Changes') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>

        <!-- Collection Connections -->
        <flux:card class="p-5 mt-6">
            <flux:heading size="lg">{{ __('Collection Connections') }}</flux:heading>
            <flux:text class="text-sm text-gray-600 dark:text-gray-400 mb-6">{{ __('Manage collections this music piece belongs to.') }}</flux:text>

            @if($music->collections->count())
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 max-h-60 overflow-y-auto mb-6">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Collection') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Page Number') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Order Number') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($music->collections as $collection)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $collection->title }}
                                        @if($collection->abbreviation)
                                            <span class="text-gray-500 dark:text-gray-400">({{ $collection->abbreviation }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $collection->pivot->page_number ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $collection->pivot->order_number ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <div class="flex items-center gap-2">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="pencil"
                                                wire:click="editCollection({{ $collection->id }})"
                                                :title="__('Edit')"
                                            />
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="trash"
                                                wire:click="removeCollection({{ $collection->id }})"
                                                wire:confirm="{{ __('Are you sure you want to remove this collection from the music piece?') }}"
                                                :title="__('Remove')"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-4 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg mb-6">
                    <flux:icon name="folder-open" class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No collections attached') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('This music piece is not attached to any collections yet.') }}</p>
                </div>
            @endif

            <!-- Collection removal message -->
            <div class="flex justify-end mb-2">
                <x-action-message on="collection-removed">
                    {{ __('Collection removed.') }}
                </x-action-message>
                <x-action-message on="collection-updated">
                    {{ __('Collection updated.') }}
                </x-action-message>
            </div>

            <!-- Add Collection Form -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <flux:heading size="sm">{{ __('Add Collection') }}</flux:heading>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ __('Assign this music piece to a new collection with page and order numbers.') }}</flux:text>

                <div class="space-y-4">
                    <flux:field required>
                        <flux:label>{{ __('Collection') }}</flux:label>
                        <flux:select
                            wire:model="selectedCollectionId"
                            searchable
                            :placeholder="__('Type to search collections...')"
                            clearable
                        >
                            <option value="">{{ __('Select a collection') }}</option>
                            @foreach ($collections as $collection)
                                <option value="{{ $collection->id }}">{{ $collection->title }}@if($collection->abbreviation) ({{ $collection->abbreviation }})@endif</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="selectedCollectionId" />
                    </flux:field>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>{{ __('Page Number') }}</flux:label>
                            <flux:input type="number" wire:model="pageNumber" :placeholder="__('Page number')" min="1" />
                            <flux:error name="pageNumber" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Order Number') }}</flux:label>
                            <flux:input wire:model="orderNumber" :placeholder="__('Order number')" />
                            <flux:error name="orderNumber" />
                        </flux:field>
                    </div>

                    <div class="flex justify-end items-center gap-4">
                        <flux:button
                            variant="primary"
                            wire:click="addCollection"
                            wire:loading.attr="disabled"
                        >
                            {{ __('Add Collection') }}
                        </flux:button>
                        <x-action-message on="collection-added">
                            {{ __('Collection added.') }}
                        </x-action-message>
                    </div>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Audit Log Modal -->
    <flux:modal wire:model="showAuditModal" max-width="4xl">
        <flux:heading size="lg">{{ __('Audit Log') }}</flux:heading>
        <flux:subheading>
            {{ __('Music Piece:') }} {{ $music->title }}
        </flux:subheading>

        <div class="mt-6">
            @if(count($audits))
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Event') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Changes') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('When') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Who') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($audits as $audit)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        @switch($audit->event)
                                            @case('created')
                                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">
                                                    {{ __('Created') }}
                                                </span>
                                                @break
                                            @case('updated')
                                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                    {{ __('Updated') }}
                                                </span>
                                                @break
                                            @case('deleted')
                                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-300">
                                                    {{ __('Deleted') }}
                                                </span>
                                                @break
                                            @default
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                                    {{ $audit->event }}
                                                </span>
                                        @endswitch
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        @if($audit->event === 'created')
                                            {{ __('Music piece was created.') }}
                                        @elseif($audit->event === 'deleted')
                                            {{ __('Music piece was deleted.') }}
                                        @else
                                            @php
                                                $oldValues = $audit->old_values ?? [];
                                                $newValues = $audit->new_values ?? [];
                                                $changes = [];
                                                foreach ($newValues as $key => $value) {
                                                    $old = $oldValues[$key] ?? null;
                                                    if ($old != $value) {
                                                        $changes[] = __($key) . ': "' . ($old ?? __('empty')) . '" → "' . ($value ?? __('empty')) . '"';
                                                    }
                                                }
                                            @endphp
                                            @if(count($changes))
                                                <ul class="list-disc list-inside space-y-1">
                                                    @foreach($changes as $change)
                                                        <li class="text-xs">{{ $change }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span class="text-gray-400 dark:text-gray-500">{{ __('No field changes recorded') }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $audit->created_at->translatedFormat('Y-m-d H:i:s') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @if($audit->user)
                                            {{ $audit->user->display_name }}
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">{{ __('System') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8">
                    <flux:icon name="logs" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No audit logs found') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('No changes have been recorded for this music piece yet.') }}</p>
                </div>
            @endif
        </div>

        <div class="mt-6 flex justify-end">
            <flux:button
                variant="ghost"
                wire:click="$set('showAuditModal', false)"
            >
                {{ __('Close') }}
            </flux:button>
        </div>
    </flux:modal>

    <!-- Edit Collection Modal -->
    <flux:modal wire:model="showEditModal" max-width="lg">
        <flux:heading size="lg">{{ __('Edit Collection Connection') }}</flux:heading>
        <flux:subheading>
            {{ __('Update page and order numbers for this collection.') }}
        </flux:subheading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Page Number') }}</flux:label>
                <flux:input
                    type="number"
                    wire:model="editingPageNumber"
                    :placeholder="__('Page number')"
                    min="1"
                />
                <flux:error name="editingPageNumber" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Order Number') }}</flux:label>
                <flux:input
                    wire:model="editingOrderNumber"
                    :placeholder="__('Order number')"
                />
                <flux:error name="editingOrderNumber" />
            </flux:field>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button
                variant="ghost"
                wire:click="$set('showEditModal', false)"
            >
                {{ __('Cancel') }}
            </flux:button>
            <flux:button
                variant="primary"
                wire:click="updateCollection"
                wire:loading.attr="disabled"
            >
                {{ __('Save Changes') }}
            </flux:button>
        </div>
    </flux:modal>
</div>