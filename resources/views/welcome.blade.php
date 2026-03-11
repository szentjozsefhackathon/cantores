<x-layouts::app.main>

    <div class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
            <main class="flex w-full flex-col-reverse lg:max-w-4xl lg:flex-row">
                <div class="w-full space-y-6">
                    <livewire:liturgical-info :welcome="true" />

                    <div class="rounded-2xl bg-linear-to-br from-indigo-600 to-purple-700 dark:from-indigo-800 dark:to-purple-900 p-6 shadow-xl">
                        <div class="mb-4 flex items-center justify-between gap-2">
                            <div>
                                <flux:heading size="lg" class="text-white!">Énekadatbázis</flux:heading>
                                <flux:text class="text-indigo-200 text-sm">Keress cím, alcím vagy énekeskönyv alapján. Pl. "Szent vagy", "ÉE 540", "Ő az Úr DÚR"</flux:text>
                            </div>
                            <a href="{{ route('music-database') }}" wire:navigate>
                                <flux:button variant="ghost" size="sm" icon-trailing="arrow-right" class="text-white! border-white/30! hover:bg-white/10!">
                                    Adatbázis
                                </flux:button>
                            </a>
                        </div>
                        <livewire:music-quick-search />
                    </div>
                </div>
            </main>
        </div>

        @if (Route::has('login'))
            <div class="h-14.5 hidden lg:block"></div>
        @endif
</x-layouts::app.main>