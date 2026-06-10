<div>
    <flux:button
        wire:click="openModal"
        variant="outline"
        color="blue"
        icon="file-text">
        Megosztás
    </flux:button>

    @if($showModal)
    <flux:modal wire:model="showModal" class="max-w-2xl">
        <flux:heading size="lg" class="mb-4">Énekrend megosztása</flux:heading>

        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400 mb-4">
            Az alábbi szöveget másolhatod és beillesztheted egy messenger csoportba:
        </flux:text>

        <!-- Text area with share content -->
        <div class="mb-4">
            <flux:field>
                <flux:textarea
                    wire:model="shareText"
                    rows="12"
                    readonly
                    class="font-mono text-sm" />
            </flux:field>
        </div>

        <!-- Secret link section (owner only) -->
        @if($isOwner)
        <div class="mb-4 pt-4 border-t border-neutral-200 dark:border-neutral-800">
            <flux:heading size="sm" class="mb-2">Titkos megosztási link</flux:heading>
            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400 mb-3">
                A titkos link segítségével megoszthatod az énekrend teljes tartalmát (privát zenékkel és kottákkal együtt) bárki számára, aki rendelkezik a linkkel.
            </flux:text>

            @if($secretLinkUrl)
            <div class="flex flex-col gap-2">
                <flux:input value="{{ $secretLinkUrl }}" readonly />
                <div class="flex gap-2">
                    <flux:button
                        wire:click="$dispatch('copy-to-clipboard', '{{ $secretLinkUrl }}')"
                        variant="outline"
                        size="sm"
                        icon="clipboard-copy">
                        Link másolása
                    </flux:button>
                    <flux:button
                        wire:click="deleteSecretLink"
                        variant="outline"
                        color="red"
                        size="sm"
                        icon="trash">
                        Link törlése
                    </flux:button>
                </div>
            </div>
            @else
            <flux:button
                wire:click="generateSecretLink"
                variant="outline"
                color="blue"
                size="sm"
                icon="link">
                Titkos link generálása
            </flux:button>
            @endif
        </div>
        @endif

        <!-- Action buttons -->
        <div class="flex gap-3 justify-end">
            <flux:button
                wire:click="copyToClipboard"
                variant="primary"
                icon="clipboard-copy">
                Másolás a vágólapra
            </flux:button>
            <flux:button
                wire:click="closeModal"
                variant="outline">
                Bezárás
            </flux:button>
        </div>
    </flux:modal>
    @endif

    <script>
        document.addEventListener('livewire:navigated', () => {
            Livewire.on('copy-to-clipboard', (text) => {
                navigator.clipboard.writeText(text).then(() => {
                    window.dispatchEvent(new CustomEvent('show-toast', { detail: { message: 'Szöveg másolva a vágólapra!', type: 'success' } }));
                }).catch(() => {
                    window.dispatchEvent(new CustomEvent('show-toast', { detail: { message: 'Hiba a másolás során', type: 'error' } }));
                });
            });
        });
    </script>
</div>
