<div
    x-data="{
        toasts: [],
        show(message, type) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, type: type ?? 'success', visible: true });
            setTimeout(() => this.hide(id), 3000);
        },
        hide(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) { toast.visible = false; }
            setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 300);
        }
    }"
    @toast.window="show($event.detail.message, $event.detail.type)"
    class="fixed top-4 left-1/2 -translate-x-1/2 z-50 flex flex-col items-center gap-2 pointer-events-none"
    x-cloak>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="toast.visible"
            :class="toast.type === 'error'
                ? 'bg-red-100 text-red-800 border-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-800'
                : 'bg-green-100 text-green-800 border-green-300 dark:bg-green-900 dark:text-green-300 dark:border-green-800'"
            class="px-6 py-3 rounded shadow-lg border pointer-events-auto"
            style="min-width: 300px; max-width: 90vw;"
            x-text="toast.message"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-4">
        </div>
    </template>
</div>
