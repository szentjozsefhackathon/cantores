<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-8">
        <flux:heading size="2xl">{{ __('Verify Music Data') }}</flux:heading>
        <flux:subheading>{{ __('Select a music piece to verify its fields and relations') }}</flux:subheading>
    </div>

    <!-- Action messages -->
    <div class="mb-4">
        <x-action-message on="verification-updated" />
        <x-action-message on="error" />
    </div>

    @if (!$showVerification)
    <!-- Selection Phase -->
    <div class="max-w-3xl mx-auto">
        <flux:heading size="lg" class="mb-4">{{ __('Select Music to Verify') }}</flux:heading>
        
        <!-- Search input -->
        <div class="mb-6">
            <flux:field>
                <flux:label>{{ __('Search music by title, subtitle, or custom ID') }}</flux:label>
                <flux:input
                    wire:model.live="search"
                    :placeholder="__('Type to search...')"
                    icon="magnifying-glass" />
            </flux:field>
        </div>

        <!-- Search results -->
        @if (count($searchResults) > 0)
        <div class="space-y-3 mb-6">
            @foreach ($searchResults as $music)
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer"
                 wire:click="selectMusic({{ $music['id'] }})">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-bold text-lg">{{ $music['title'] }}</div>
                        @if ($music['subtitle'])
                        <div class="text-gray-600 dark:text-gray-400">{{ $music['subtitle'] }}</div>
                        @endif
                        @if ($music['custom_id'])
                        <div class="text-sm font-mono text-gray-500 dark:text-gray-500">{{ $music['custom_id'] }}</div>
                        @endif
                    </div>
                    <flux:icon name="chevron-right" class="h-5 w-5 text-gray-400" />
                </div>
                <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Collections:') }} {{ $music['collections_count'] }},
                    {{ __('Verifications:') }} {{ $music['verifications_count'] }}
                </div>
            </div>
            @endforeach
        </div>
        @elseif ($search)
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <flux:icon name="document-magnifying-glass" class="h-12 w-12 mx-auto mb-4" />
            <p>{{ __('No music found matching your search.') }}</p>
        </div>
        @else
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <flux:icon name="document-text" class="h-12 w-12 mx-auto mb-4" />
            <p>{{ __('Start typing to search for music to verify.') }}</p>
        </div>
        @endif

        <!-- Or use music-search component -->
        <div class="mt-8">
            <flux:heading size="md" class="mb-4">{{ __('Or select from music search') }}</flux:heading>
            <livewire:music-search selectable="true" source=".verifyMusic" />
        </div>
    </div>
    @else
    <!-- Verification Phase -->
    <div class="space-y-8">
        <!-- Header with music info and back button -->
        <div class="flex items-center justify-between">
            <div>
                <flux:button
                    variant="ghost"
                    icon="arrow-left"
                    wire:click="resetSelection">
                    {{ __('Back to selection') }}
                </flux:button>
            </div>
            <div class="text-right">
                <flux:heading size="lg">{{ $music->title }}</flux:heading>
                @if ($music->subtitle)
                <div class="text-gray-600 dark:text-gray-400">{{ $music->subtitle }}</div>
                @endif
                @if ($music->custom_id)
                <div class="text-sm font-mono text-gray-500 dark:text-gray-500">{{ $music->custom_id }}</div>
                @endif
            </div>
        </div>

        <!-- Verification stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold">{{ $verificationStats['total'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('Total Fields') }}</div>
            </div>
            <div class="border border-green-200 dark:border-green-800 rounded-lg p-4 text-center bg-green-50 dark:bg-green-900/20">
                <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ $verificationStats['verified'] ?? 0 }}</div>
                <div class="text-sm text-green-600 dark:text-green-400">{{ __('Verified') }}</div>
            </div>
            <div class="border border-red-200 dark:border-red-800 rounded-lg p-4 text-center bg-red-50 dark:bg-red-900/20">
                <div class="text-2xl font-bold text-red-700 dark:text-red-300">{{ $verificationStats['rejected'] ?? 0 }}</div>
                <div class="text-sm text-red-600 dark:text-red-400">{{ __('Rejected') }}</div>
            </div>
            <div class="border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 text-center bg-yellow-50 dark:bg-yellow-900/20">
                <div class="text-2xl font-bold text-yellow-700 dark:text-yellow-300">{{ $verificationStats['pending'] ?? 0 }}</div>
                <div class="text-sm text-yellow-600 dark:text-yellow-400">{{ __('Pending') }}</div>
            </div>
        </div>

        <!-- Progress bar -->
        <div>
            <div class="flex justify-between text-sm mb-1">
                <span>{{ __('Verification Progress') }}</span>
                <span>{{ $verificationStats['progress'] ?? 0 }}%</span>
            </div>
            <x-mary-progress :value="$verificationStats['progress'] ?? 0" />

        </div>

        <!-- Batch actions -->
        <div class="flex flex-wrap gap-3">
            <flux:button
                variant="primary" color="green"
                icon="check-circle"
                wire:click="verifyAll('verified')"
                wire:confirm="{{ __('Are you sure you want to mark all pending fields as verified?') }}">
                {{ __('Verify All Pending') }}
            </flux:button>
            <flux:button
                variant="danger"
                icon="x-circle"
                wire:click="verifyAll('rejected')"
                wire:confirm="{{ __('Are you sure you want to mark all pending fields as rejected?') }}">
                {{ __('Reject All Pending') }}
            </flux:button>
        </div>

        <!-- Fields verification table -->
        <div class="overflow-hidden border border-gray-200 dark:border-gray-700 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            {{ __('Field / Relation') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            {{ __('Current Value') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            {{ __('Status') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            {{ __('Notes') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($verifiableFields as $field)
                    @php
                        $key = $field['name'] . ':' . ($field['pivot_reference'] ?? '0');
                        $status = $fieldStatuses[$key] ?? 'pending';
                        $notes = $fieldNotes[$key] ?? '';
                    @endphp
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $field['label'] }}
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $field['type'] === 'field' ? __('Field') : __('Relation') }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                            @if (is_array($field['value']))
                                @foreach ($field['value'] as $k => $v)
                                    <div><span class="font-medium">{{ __(ucfirst($k)) }}:</span> {{ $v ?? '-' }}</div>
                                @endforeach
                            @elseif (is_bool($field['value']))
                                {{ $field['value'] ? __('Yes') : __('No') }}
                            @else
                                {{ $field['value'] ?? __('Empty') }}
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @switch($status)
                                @case('verified')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300">
                                        <flux:icon name="check-circle" class="h-3 w-3 mr-1" />
                                        {{ __('Verified') }}
                                    </span>
                                    @break
                                @case('rejected')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300">
                                        <flux:icon name="x-circle" class="h-3 w-3 mr-1" />
                                        {{ __('Rejected') }}
                                    </span>
                                    @break
                                @default
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-300">
                                        <flux:icon name="clock" class="h-3 w-3 mr-1" />
                                        {{ __('Pending') }}
                                    </span>
                            @endswitch
                        </td>
                        <td class="px-6 py-4">
                            <flux:input
                                wire:model.live="fieldNotes.{{ $key }}"
                                size="sm"
                                :placeholder="__('Add notes...')"
                                class="w-full" />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex flex-wrap gap-2">
                                <flux:button
                                    variant="primary" color="green"
                                    size="sm"
                                    icon="check-circle"
                                    wire:click="verifyField('{{ $field['name'] }}', {{ $field['pivot_reference'] ?? 'null' }}, 'verified', $fieldNotes['{{ $key }}'] ?? '')"
                                    :disabled="$status === 'verified'"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50 cursor-not-allowed">
                                    <span>{{ __('Verify') }}</span>
                                </flux:button>
                                <flux:button
                                    variant="danger"
                                    size="sm"
                                    icon="x-circle"
                                    wire:click="verifyField('{{ $field['name'] }}', {{ $field['pivot_reference'] ?? 'null' }}, 'rejected', $fieldNotes['{{ $key }}'] ?? '')"
                                    :disabled="$status === 'rejected'"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50 cursor-not-allowed">
                                    <span>{{ __('Reject') }}</span>
                                </flux:button>
                                <flux:button
                                    variant="outline"
                                    size="sm"
                                    icon="trash"
                                    wire:click="unverifyField('{{ $field['name'] }}', {{ $field['pivot_reference'] ?? 'null' }})"
                                    :disabled="$status === 'pending'"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50 cursor-not-allowed">
                                    <span>{{ __('Unverify') }}</span>
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <flux:heading size="lg" class="mb-4">{{ __('Verification Summary') }}</flux:heading>
            <p class="text-gray-700 dark:text-gray-300 mb-4">
                {{ __('Verification helps ensure data quality. Verified fields are marked as correct, rejected fields need correction. Use unverify to remove verification.') }}
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center">
                    <flux:icon name="check-circle" class="h-5 w-5 text-green-500 mr-2" />
                    <span class="text-sm">{{ __('Verified: Data is correct') }}</span>
                </div>
                <div class="flex items-center">
                    <flux:icon name="x-circle" class="h-5 w-5 text-red-500 mr-2" />
                    <span class="text-sm">{{ __('Rejected: Data is incorrect') }}</span>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>