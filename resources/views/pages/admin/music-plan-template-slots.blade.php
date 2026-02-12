<x-pages::admin.layout
    :heading="$template->name"
    :subheading="__('Manage slots for this template')">
    <div class="space-y-6">
        <!-- Template Info -->
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-medium">{{ $template->name }}</h3>
                    @if ($template->description)
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $template->description }}</p>
                    @endif
                    <div class="mt-2 flex items-center gap-4">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $template->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $template->is_active ? __('Active') : __('Inactive') }}
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __(':count slots', ['count' => $template->slots->count()]) }}
                        </span>
                    </div>
                </div>
                <flux:button
                    variant="ghost"
                    icon="arrow-left"
                    :href="route('admin.music-plan-templates')"
                    wire:navigate>
                    {{ __('Back to Templates') }}
                </flux:button>
            </div>
        </div>

        <!-- Action messages -->
        <div class="flex justify-end">
            <x-action-message on="slot-added">
                {{ __('Slot added.') }}
            </x-action-message>
            <x-action-message on="slot-updated">
                {{ __('Slot updated.') }}
            </x-action-message>
            <x-action-message on="slot-removed">
                {{ __('Slot removed.') }}
            </x-action-message>
        </div>

        <!-- Slots Management -->
        <div class="rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-medium">{{ __('Template Slots') }}</h3>
                <flux:button
                    variant="primary"
                    icon="plus"
                    wire:click="showAddSlot"
                    :disabled="count($availableSlots) === 0">
                    {{ __('Add Slot') }}
                </flux:button>
            </div>

            @if ($template->slots->isEmpty())
            <div class="p-8 text-center">
                <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                    <flux:icon name="list" class="h-12 w-12 mb-2 opacity-50" />
                    <p class="text-lg font-medium">{{ __('No slots added yet') }}</p>
                    <p class="text-sm mt-1">{{ __('Add slots to define the structure of this template') }}</p>
                </div>
            </div>
            @else
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($template->slots->sortBy('pivot.sequence') as $slot)
                <div class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <div class="flex items-start gap-4">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-sm font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                            {{ $slot->pivot->sequence }}
                        </div>
                        <div>
                            <h4 class="font-medium">{{ $slot->name }}</h4>
                            @if ($slot->description)
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $slot->description }}</p>
                            @endif
                            <div class="mt-2 flex items-center gap-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $slot->pivot->is_included_by_default ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' }}">
                                    {{ $slot->pivot->is_included_by_default ? __('Included by default') : __('Advanced/Optional') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="pencil"
                            wire:click="showEditSlot({{ json_encode($slot) }})"
                            :title="__('Edit')" />

                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="trash"
                            wire:click="removeSlot({{ $slot->id }})"
                            wire:confirm="{{ __('Are you sure you want to remove this slot from the template?') }}"
                            :title="__('Remove')" />
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        <!-- Available Slots Info -->
        @if (count($availableSlots) === 0)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20">
            <div class="flex items-center gap-3">
                <flux:icon name="information-circle" class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                <div>
                    <p class="font-medium text-amber-800 dark:text-amber-300">{{ __('No more slots available') }}</p>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
                        {{ __('All existing slots have been added to this template. Create new slots to add more.') }}
                    </p>
                </div>
            </div>
        </div>
        @else
        <div class="rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-medium">{{ __('Available Slots') }}</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ __(':count slots available to add', ['count' => count($availableSlots)]) }}
                </p>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($availableSlots as $slot)
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <h4 class="font-medium">{{ $slot->name }}</h4>
                        @if ($slot->description)
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $slot->description }}</p>
                        @endif
                    </div>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="plus"
                        wire:click="showAddSlot({{ $slot->id }})"
                        :title="__('Add to template')" />
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <!-- Add Slot Modal -->
    <flux:modal wire:model="showAddSlotModal">
        <flux:heading>{{ __('Add Slot to Template') }}</flux:heading>

        <form wire:submit="addSlot" class="space-y-4">
            <flux:field>
                <flux:label for="slot-id">{{ __('Slot') }} *</flux:label>
                <flux:select
                    id="slot-id"
                    wire:model="slot_id"
                    required>
                    <option value="">{{ __('Select a slot') }}</option>
                    @foreach ($availableSlots as $slot)
                    <option value="{{ $slot->id }}">{{ $slot->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="slot_id" />
            </flux:field>

            <flux:field>
                <flux:label for="sequence">
                    {{ __('Sequence Number') }} *
                </flux:label>

                <flux:input
                    id="sequence"
                    type="number"
                    wire:model="sequence"
                    min="1"
                    required />

                <flux:description>
                    {{ __('Determines the order of slots in the template') }}
                </flux:description>

                <flux:error name="sequence" />
            </flux:field>
            <flux:field>
                <flux:checkbox
                    id="is-included-by-default"
                    wire:model="is_included_by_default"
                    label="{{ __('Included by default in music plans') }}" />
                <flux:description>{{ __('If unchecked, this slot will be marked as advanced/optional') }}</flux:description>
                <flux:error name="is_included_by_default" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-4">
                <flux:button
                    variant="ghost"
                    wire:click="$set('showAddSlotModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    type="submit"
                    variant="primary">
                    {{ __('Add Slot') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Slot Modal -->
    <flux:modal wire:model="showEditSlotModal">
        <flux:heading>{{ __('Edit Slot in Template') }}</flux:heading>

        @if ($editingSlotPivot)
        <div class="mb-4 rounded-lg bg-gray-100 p-3 dark:bg-gray-800">
            <p class="font-medium">{{ $editingSlotPivot['name'] }}</p>
            @if ($editingSlotPivot['description'])
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $editingSlotPivot['description'] }}</p>
            @endif
        </div>
        @endif

        <form wire:submit="updateSlot" class="space-y-4">
            <flux:field>
                <flux:label for="edit-sequence">{{ __('Sequence Number') }} *</flux:label>
                <flux:input
                    id="edit-sequence"
                    type="number"
                    wire:model="edit_sequence"
                    min="1"
                    required />
                <flux:description>{{ __('Determines the order of slots in the template') }}</flux:description>
                <flux:error name="edit_sequence" />
            </flux:field>

            <flux:field>
                <flux:checkbox
                    id="edit-is-included-by-default"
                    wire:model="edit_is_included_by_default"
                    label="{{ __('Included by default in music plans') }}" />
                <flux:description>{{ __('If unchecked, this slot will be marked as advanced/optional') }}</flux:description>
                <flux:error name="edit_is_included_by_default" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-4">
                <flux:button
                    variant="ghost"
                    wire:click="$set('showEditSlotModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    type="submit"
                    variant="primary">
                    {{ __('Update Slot') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</x-pages::admin.layout>