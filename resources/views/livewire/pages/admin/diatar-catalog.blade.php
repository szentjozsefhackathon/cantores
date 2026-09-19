<x-pages::admin.layout title="Diatár katalógus">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="xl">Diatár katalógus</flux:heading>
                <flux:text class="mt-1 text-sm text-gray-600 dark:text-gray-400">Csak metaadatok; a forrás DTX tartalma nem kerül tárolásra.</flux:text>
            </div>
            <flux:button wire:click="sync" wire:confirm="Elindítod a Diatár katalógus frissítését?" icon="arrow-path" variant="primary">
                Frissítés sorba állítása
            </flux:button>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <flux:card class="p-4">
                <flux:text class="text-sm text-gray-500">Kötetek</flux:text>
                <flux:heading size="xl">{{ $bookCount }}</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-gray-500">Nem elérhető</flux:text>
                <flux:heading size="xl">{{ $unavailableCount }}</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-gray-500">Elavult</flux:text>
                <flux:heading size="xl">{{ $staleCount }}</flux:heading>
            </flux:card>
        </div>

        <flux:card class="space-y-3 p-5">
            <flux:heading size="lg">Utolsó sikeres frissítés</flux:heading>
            @if($lastSuccessfulRun)
                <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Forrásverzió</dt><dd class="font-mono">{{ $lastSuccessfulRun->source_revision }}</dd></div>
                    <div><dt class="text-gray-500">Befejezve</dt><dd>{{ $lastSuccessfulRun->completed_at?->format('Y-m-d H:i:s') }}</dd></div>
                    <div><dt class="text-gray-500">Indexelve</dt><dd>{{ $lastSuccessfulRun->indexed_count }}</dd></div>
                    <div><dt class="text-gray-500">Figyelmeztetések</dt><dd>{{ $lastSuccessfulRun->warning_count }}</dd></div>
                </dl>
            @else
                <flux:callout icon="information-circle">Még nem volt sikeres katalógusfrissítés.</flux:callout>
            @endif
        </flux:card>

        @if($latestRun)
            <flux:card class="space-y-3 p-5">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="lg">Legutóbbi futás</flux:heading>
                    <flux:badge color="{{ $latestRun->status === \App\Enums\DiatarSyncStatus::Failed ? 'red' : ($latestRun->warning_count > 0 ? 'amber' : 'green') }}">
                        {{ $latestRun->status->value }}
                    </flux:badge>
                </div>
                <flux:text class="text-sm">{{ $latestRun->fetched_count }} letöltve · {{ $latestRun->indexed_count }} indexelve · {{ $latestRun->skipped_count }} kihagyva</flux:text>
                @if($latestRun->error_summary)
                    <pre class="max-h-72 overflow-auto whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-800">{{ $latestRun->error_summary }}</pre>
                @endif
            </flux:card>
        @endif
    </div>
</x-pages::admin.layout>
