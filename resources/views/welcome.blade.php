<x-layouts::shell
    title="A saját liturgikus zenei műhelyed: énekrendek, kották, füzetek, vetítés"
    description="Kántoroknak és zenekarvezetőknek: állítsd össze az énekrendet, készítsd el a saját kottáidat és változataidat, és ugyanabból az anyagból nyomtass füzetet vagy vetíts. Amit egyszer megcsináltál, nem kell újra megcsinálnod."
>

    @php
        $workshopSteps = [
            [
                'icon' => 'list-bullet',
                'title' => 'Énekrend',
                'text' => 'Összeállítod, mi hangzik el vasárnap — sablonból, javaslatokból vagy tiszta lapról.',
            ],
            [
                'icon' => 'musical-note',
                'title' => 'Kotta',
                'text' => 'A saját változatod: más hangnem, más versszakok, saját kíséret, saját szólamok. Négy formátumban írhatod, vagy feltöltöd, ami már megvan.',
            ],
            [
                'icon' => 'book-open',
                'title' => 'Füzet',
                'text' => 'Ugyanabból az anyagból nyomtatható füzet — A4, A5, A6, montírozva, a zenészek kezébe.',
            ],
            [
                'icon' => 'tv',
                'title' => 'Vetítés',
                'text' => 'És ugyanabból a kivetített kép, a saját képarányodra tördelve, távirányítóval.',
            ],
        ];

        $tools = [
            [
                'icon' => 'magnifying-glass',
                'title' => 'Énektár',
                'text' => 'Énekek, gyűjtemények, szerzők',
                'route' => 'music-database',
            ],
            [
                'icon' => 'list-bullet',
                'title' => 'Énekrendek',
                'text' => 'Mit énekelnek mások?',
                'route' => 'music-plans',
            ],
            [
                'icon' => 'pencil-square',
                'title' => 'Kottaszerkesztő',
                'short' => 'Kottázás',
                'text' => 'Írd meg a saját változatod',
                'route' => 'score.preview',
            ],
        ];
    @endphp

    <div class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
            <main class="flex w-full flex-col-reverse lg:max-w-6xl lg:flex-row">
                <div class="w-full space-y-6">

                    <section class="rounded-2xl border border-zinc-200 bg-white px-5 py-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <h1 class="text-2xl font-bold text-zinc-900 sm:text-3xl dark:text-white">
                            A saját liturgikus zenei műhelyed
                        </h1>
                        <p class="mt-2 text-zinc-600 dark:text-zinc-400">
                            Énekrend, kotta, füzet és vetítés — ugyanabból az anyagból, egy helyen.
                        </p>

                        <details class="group mt-3">
                            <summary class="flex w-fit cursor-pointer list-none items-center gap-1 text-sm font-medium text-indigo-600 [&::-webkit-details-marker]:hidden dark:text-indigo-400">
                                Hogyan működik?
                                <flux:icon name="chevron-down" class="size-4 transition-transform group-open:rotate-180" />
                            </summary>

                            <div class="mt-4 space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                                <flux:text class="max-w-3xl">
                                    Nem énektár, nem kottaszerkesztő, nem vetítőprogram — hanem mindez együtt,
                                    egyetlen munkamenetben. Az énekrendtől a kivetített képig ugyanabból az
                                    anyagból dolgozol.
                                </flux:text>

                                <p class="max-w-3xl font-medium text-zinc-800 dark:text-zinc-100">
                                    Ami nincs készen, azt itt megcsinálod. Amit egyszer megcsináltál, azt többé
                                    nem kell újra megcsinálnod.
                                </p>

                                <ol class="flex flex-col gap-2 sm:flex-row sm:items-stretch">
                                    @foreach ($workshopSteps as $index => $step)
                                        <li class="flex flex-1 flex-col items-center gap-2 sm:flex-row">
                                            @if ($index > 0)
                                                <flux:icon
                                                    name="arrow-right"
                                                    class="size-5 shrink-0 rotate-90 text-zinc-400 sm:rotate-0 dark:text-zinc-500"
                                                    aria-hidden="true"
                                                />
                                            @endif
                                            <div class="h-full w-full rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                                                <div class="flex items-center gap-2">
                                                    <flux:icon :name="$step['icon']" class="size-5 text-indigo-600 dark:text-indigo-400" />
                                                    <flux:heading size="sm">{{ $step['title'] }}</flux:heading>
                                                </div>
                                                <flux:text class="mt-2 text-sm">{{ $step['text'] }}</flux:text>
                                            </div>
                                        </li>
                                    @endforeach
                                </ol>

                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <flux:button href="{{ route('register') }}" variant="primary" icon-trailing="arrow-right">
                                        Kezdd el a saját műhelyed
                                    </flux:button>
                                    <flux:button href="{{ route('guide') }}" variant="ghost" wire:navigate>
                                        Nézd meg, mit tud
                                    </flux:button>
                                    <flux:text class="text-xs">Ingyenes, önkéntes alapon működik.</flux:text>
                                </div>

                                <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                                    <flux:text class="max-w-3xl text-sm">
                                        Egy közösség ritkán énekel pontosan úgy, ahogy az a gyűjteményben szerepel.
                                        Más hangnem, más versszakok, saját kíséret, egy bevált átirat. A Cantores azt
                                        a munkát gyűjti egy helyre, amit emiatt eddig külön mappákban, gépeken és
                                        pendrive-okon tartottál.
                                    </flux:text>
                                    <flux:text class="max-w-3xl text-sm">
                                        Semmit nem kell telepíteni. Otthon, a templomban és a próbán ugyanaz az anyag —
                                        akkor is, ha a plébánia kicseréli a laptopot.
                                    </flux:text>
                                    <flux:text class="max-w-3xl text-sm">
                                        Kántoroknak, szkólavezetőknek, kórusvezetőknek és gitáros zenekarok vezetőinek —
                                        azoknak, akik nemcsak tartalmat keresnek, hanem saját zenei munkát végeznek.
                                    </flux:text>
                                </div>
                            </div>
                        </details>
                    </section>

                    <div class="flex gap-2 sm:gap-3">
                        @foreach ($tools as $tool)
                            <a
                                href="{{ route($tool['route']) }}"
                                wire:navigate
                                class="flex flex-1 flex-col items-center justify-center rounded-xl border border-zinc-200 bg-white px-3 py-2 text-center transition-colors hover:border-indigo-300 hover:bg-indigo-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/40"
                            >
                                <span class="flex items-center gap-1.5 text-sm font-semibold text-zinc-900 dark:text-white">
                                    <flux:icon :name="$tool['icon']" class="size-4 shrink-0 text-indigo-600 dark:text-indigo-400" />
                                    <span class="sm:hidden">{{ $tool['short'] ?? $tool['title'] }}</span>
                                    <span class="hidden sm:inline">{{ $tool['title'] }}</span>
                                </span>
                                <span class="hidden text-xs text-zinc-500 sm:block dark:text-zinc-400">{{ $tool['text'] }}</span>
                            </a>
                        @endforeach
                    </div>

                    <livewire:liturgical-info :welcome="true" />

                    <div class="rounded-2xl bg-linear-to-br from-indigo-600 to-purple-700 dark:from-indigo-800 dark:to-purple-900 p-6 shadow-xl">
                        <div class="mb-4 flex items-center justify-between gap-2">
                            <div>
                                <flux:heading size="lg" class="text-white!">Gyorskeresés</flux:heading>
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

                    <x-external-links class="mt-6" />
                </div>
            </main>
        </div>

</x-layouts::shell>
