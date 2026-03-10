<div class="mx-auto w-full px-4 sm:px-6 lg:px-8">
    <!-- Action messages -->
    <div class="mb-4 flex justify-end">
        <x-action-message on="music-deleted">
            {{ __('Music piece deleted.') }}
        </x-action-message>
        <x-action-message on="error" />
    </div>

    <div class="space-y-6">
        <!-- Search and Actions -->

        <!-- Container -->
        <div class="mx-auto p-4 sm:p-6">
            <!-- Filters card -->
            <div class="rounded-2xl border p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold ">Zeneművek keresése</h2>
                    </div>

                </div>

                @include('partials.music-browser-filters')
            </div>

            <!-- Table card -->
            <div class="mt-4 rounded-2xl border shadow-sm">
                <!-- Table header / meta row -->
                <div class="flex flex-col gap-2 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        @auth
                        <flux:button
                            variant="primary"
                            icon="plus"
                            wire:click="create">
                            {{ __('Create Music Piece') }}
                        </flux:button>
                        @endauth
                        @can('mergeAny', \App\Models\Music::class)
                        <flux:button
                            variant="filled"
                            icon="combine"
                            wire:click="merge"
                            :disabled="!$this->canMerge"
                            :title="$this->canMerge ? __('Merge selected songs') : __('Select exactly 2 songs to merge')">
                            {{ __('Merge Songs') }}
                        </flux:button>
                        @endcan

                    </div>
                </div>

                <!-- Table scroll container -->
                <div class="overflow-x-auto p-4">
                    @include('partials.music-browser-table', ['mode' => 'manage'])
                </div>
            </div>
        </div>

    </div>

    <!-- Modals outside main content for single root -->
    <flux:modal wire:model="showCreateModal" max-width="lg">
        <flux:heading size="lg">{{ __('Create Music Piece') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field required>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:input
                    wire:model="title"
                    :placeholder="__('Enter music piece title')"
                    autofocus />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Subtitle') }}</flux:label>
                <flux:input
                    wire:model="subtitle"
                    :placeholder="__('Enter subtitle')" />
                <flux:error name="subtitle" />
            </flux:field>

            <flux:field>
                <flux:checkbox
                    wire:model="isPrivate"
                    :label="__('Make this music piece private (only visible to you)')" />
                <flux:description>{{ __('Private music pieces are only visible to you and cannot be seen by other users.') }}</flux:description>
            </flux:field>

        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button
                variant="ghost"
                wire:click="$set('showCreateModal', false)">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button
                variant="primary"
                wire:click="store">
                {{ __('Create') }}
            </flux:button>
        </div>
    </flux:modal>

    <flux:modal wire:model="showAuditModal" max-width="4xl">
        <flux:heading size="lg">{{ __('Audit Log') }}</flux:heading>
        <flux:subheading>
            {{ __('Music Piece:') }} {{ $auditingMusic->title ?? '' }}
        </flux:subheading>

        <div class="mt-6">
            @if($auditingMusic && count($audits))
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
                wire:click="$set('showAuditModal', false)">
                {{ __('Close') }}
            </flux:button>
        </div>
    </flux:modal>

</div>