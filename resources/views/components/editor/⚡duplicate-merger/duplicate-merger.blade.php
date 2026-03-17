<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Duplikátumok egyesítése</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">
            Azon énekek listája, amelyeknek van "Duplikátum" típusú kapcsolata. Kattints az "Egyesítés" gombra a duplikátum pár gyors egyesítéséhez.
        </p>
    </div>

    <div class="mb-6">
        <flux:input
            wire:model.live="search"
            placeholder="Keresés cím, azonosító vagy alcím alapján..."
            icon="search"
            class="w-full max-w-md"
        />
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" wire:click="sort('title')">
                            Cím
                            @if($sortBy === 'title')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Gyűjtemények
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Duplikátumok száma
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Első duplikátum
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Műveletek
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($musics as $music)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('music-view', $music) }}" class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 hover:underline">
                                    {{ $music->title }}
                                </a>
                                @if($music->subtitle)
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $music->subtitle }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-normal text-sm text-gray-500 dark:text-gray-400">
                                @if($music->collections->isNotEmpty())
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($music->collections as $collection)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                {{ $collection->abbreviation ?? $collection->title }}
                                                @if($collection->pivot->order_number)
                                                    <span class="ml-1">{{ $collection->pivot->order_number }}</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-400">–</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    {{ $music->duplicate_count }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                @php
                                    $firstDuplicate = $music->directMusicRelations
                                        ->where('relationship_type', \App\MusicRelationshipType::Duplicate->value)
                                        ->first();
                                @endphp
                                @if($firstDuplicate)
                                    <div class="flex items-center">
                                        <div>
                                            <div class="font-medium">{{ $firstDuplicate->relatedMusic->title }}</div>
                                            @if($firstDuplicate->relatedMusic->collections->isNotEmpty())
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    @foreach($firstDuplicate->relatedMusic->collections as $collection)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                            {{ $collection->abbreviation ?? $collection->title }}
                                                            @if($collection->pivot->order_number)
                                                                <span class="ml-1">{{ $collection->pivot->order_number }}</span>
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="text-xs text-gray-400">–</div>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <span class="text-gray-400">–</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <flux:button
                                    wire:click="merge({{ $music->id }})"
                                    variant="primary"
                                    size="sm"
                                    icon="combine"
                                >
                                    Egyesítés
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <flux:icon name="music-note" class="h-12 w-12 text-gray-300 dark:text-gray-600 mb-4" />
                                    <p class="text-lg font-medium">Nincsenek duplikátum kapcsolatok</p>
                                    <p class="mt-2">Nincs olyan ének, amelynek duplikátum típusú kapcsolata lenne.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($musics->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $musics->links() }}
            </div>
        @endif
    </div>
</div>
