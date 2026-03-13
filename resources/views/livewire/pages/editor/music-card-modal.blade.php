<div x-data="{ open: false }"
     x-effect="open = $wire.musicId !== null"
     @show-music-card-modal.window="$wire.dispatch('show-music-card-modal', { musicId: $event.detail.musicId })">
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex min-h-full items-center justify-center p-4">
            <!-- Overlay -->
            <div x-show="open" @click="open = false; $wire.call('close')" class="fixed inset-0 bg-black/50"></div>

            <!-- Modal -->
            <div class="relative bg-white dark:bg-gray-900 rounded-lg shadow-xl max-w-md w-full p-6">
                <button @click="open = false; $wire.call('close')" class="absolute top-4 right-4 z-10 text-gray-400 hover:text-gray-600 dark:hover:text-gray-400">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>

                @if($musicId)
                    <div class="pr-8">
                        <livewire:pages.editor.music-card-display :music-id="$musicId" :key="$musicId" />
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
