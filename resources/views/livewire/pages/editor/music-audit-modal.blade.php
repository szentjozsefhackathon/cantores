<flux:modal wire:model="show" max-width="4xl">
    <flux:heading size="lg">{{ __('Audit Log') }}</flux:heading>
    <flux:subheading>
        {{ __('Music Piece:') }} {{ $music?->title ?? '' }}
    </flux:subheading>

    <div class="mt-6">
        @if($music && $audits->isNotEmpty())
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
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">{{ __('Created') }}</span>
                                        @break
                                    @case('updated')
                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">{{ __('Updated') }}</span>
                                        @break
                                    @case('deleted')
                                        <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-300">{{ __('Deleted') }}</span>
                                        @break
                                    @default
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-900 dark:text-gray-300">{{ $audit->event }}</span>
                                @endswitch
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                @if($audit->event === 'created')
                                    {{ __('Music piece was created.') }}
                                @elseif($audit->event === 'deleted')
                                    {{ __('Music piece was deleted.') }}
                                @else
                                    @php
                                        $changes = [];
                                        foreach ($audit->new_values ?? [] as $key => $value) {
                                            $old = ($audit->old_values ?? [])[$key] ?? null;
                                            if ($old != $value) {
                                                $changes[] = __($key) . ': "' . ($old ?? __('empty')) . '" → "' . ($value ?? __('empty')) . '"';
                                            }
                                        }
                                    @endphp
                                    @if($changes)
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
        <flux:button variant="ghost" wire:click="$set('show', false)">
            {{ __('Close') }}
        </flux:button>
    </div>
</flux:modal>
