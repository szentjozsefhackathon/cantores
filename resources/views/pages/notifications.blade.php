<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>
        @if($notifications->count() > 0)
            <button
                wire:click="markAllAsRead"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
                Mark all as read
            </button>
        @endif
    </div>

    @if($notifications->count() > 0)
        <div class="space-y-4">
            @foreach($notifications as $notification)
                <div
                    class="p-4 border rounded-lg {{ $notification->isReadBy(auth()->user()) ? 'bg-gray-50' : 'bg-white border-blue-300' }}"
                    wire:key="{{ $notification->id }}"
                >
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $notification->resource_type }}
                                </span>
                                @if(!$notification->isReadBy(auth()->user()))
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        New
                                    </span>
                                @endif
                            </div>
                            <p class="text-gray-800">{{ $notification->message }}</p>
                            <div class="mt-2 text-sm text-gray-600">
                                Reported by <span class="font-medium">{{ $notification->reporter?->display_name ?? 'Unknown' }}</span>
                                on <span class="font-medium">{{ $notification->resource_title ?? 'Unknown resource' }}</span>
                                · {{ $notification->created_at->diffForHumans() }}
                            </div>
                        </div>
                        @if(!$notification->isReadBy(auth()->user()))
                            <button
                                wire:click="markAsRead('{{ $notification->id }}')"
                                class="ml-4 px-3 py-1 text-sm bg-green-100 text-green-800 rounded hover:bg-green-200 focus:outline-none"
                            >
                                Mark as read
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @else
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No notifications</h3>
            <p class="mt-1 text-sm text-gray-500">You're all caught up!</p>
        </div>
    @endif
</div>