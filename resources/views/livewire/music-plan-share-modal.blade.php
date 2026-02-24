<div>
    <flux:button
        wire:click="openModal"
        variant="outline"
        color="blue"
        icon="document-text">
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
                    // Show success message
                    const notification = document.createElement('div');
                    notification.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg';
                    notification.textContent = 'Szöveg másolva a vágólapra!';
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                }).catch(() => {
                    alert('Hiba a másolás során');
                });
            });
        });
    </script>
</div>
