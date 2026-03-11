<?php

namespace App\Livewire\Pages;

use App\Models\Author;
use App\Models\Collection;
use App\Models\Music;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public function rendering(View $view): void
    {
        $layout = Auth::check() ? 'layouts::app' : 'layouts::app.main';
        $view->layout($layout);
    }

    #[Computed]
    public function musicCount(): int
    {
        return Music::public()->count();
    }

    #[Computed]
    public function collectionCount(): int
    {
        return Collection::public()->count();
    }

    #[Computed]
    public function authorCount(): int
    {
        return Author::public()->count();
    }

    #[Computed]
    public function collections(): \Illuminate\Support\Collection
    {
        return Collection::public()
            ->with('genres')
            ->withCount('music')
            ->orderByDesc('music_count')
            ->get();
    }

    #[Computed]
    public function recentMusics(): \Illuminate\Support\Collection
    {
        return Music::public()
            ->with(['authors', 'collections'])
            ->latest()
            ->limit(8)
            ->get();
    }
}
?>

<div>
    {{-- Hero Section --}}
    <div class="relative overflow-hidden bg-linear-to-br from-blue-600 via-indigo-700 to-purple-800 dark:from-blue-900 dark:via-indigo-900 dark:to-purple-950">
        <div class="absolute inset-0 opacity-10 pointer-events-none">
            <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-white blur-3xl"></div>
            <div class="absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-white blur-3xl"></div>
        </div>
        <div class="relative mx-auto max-w-4xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
            <div class="text-center">
                <div class="mb-4 flex justify-center">
                    <div class="flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-sm text-white backdrop-blur-sm">
                        <flux:icon name="list-music" variant="mini" class="h-4 w-4" />
                        Cantores.hu
                    </div>
                </div>
                <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">
                    Liturgikus énekek és<br class="hidden sm:block" />
                    zeneművek adatbázisa
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-blue-100 dark:text-blue-200">
                    Kereshető, közösség által gondozott adatbázis liturgikus énekekkel, gyűjteményekkel és szerzőkkel.
                </p>
                <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('musics') }}" wire:navigate>
                        <flux:button variant="primary" icon="music" class="bg-white! text-indigo-700! hover:bg-blue-50! font-semibold shadow-lg">
                            Zeneművek böngészése
                        </flux:button>
                    </a>
                    <a href="{{ route('collections') }}" wire:navigate>
                        <flux:button variant="ghost" icon="folder" class="border-white/30! text-white! hover:bg-white/10!">
                            Gyűjtemények
                        </flux:button>
                    </a>
                    <a href="{{ route('authors') }}" wire:navigate>
                        <flux:button variant="ghost" icon="users" class="border-white/30! text-white! hover:bg-white/10!">
                            Szerzők
                        </flux:button>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Bar --}}
    <div class="border-b border-gray-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-3 divide-x divide-gray-200 dark:divide-zinc-700">
                <a href="{{ route('musics') }}" wire:navigate class="group flex flex-col items-center py-6 text-center hover:bg-gray-50 dark:hover:bg-zinc-800 transition">
                    <flux:icon name="music" class="mb-2 h-6 w-6 text-indigo-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition" />
                    <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 sm:text-3xl">{!! number_format($this->musicCount, 0, ',', '&nbsp;') !!}</span>
                    <span class="mt-1 text-sm text-gray-600 dark:text-gray-400">zenemű</span>
                </a>
                <a href="{{ route('collections') }}" wire:navigate class="group flex flex-col items-center py-6 text-center hover:bg-gray-50 dark:hover:bg-zinc-800 transition">
                    <flux:icon name="folder" class="mb-2 h-6 w-6 text-indigo-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition" />
                    <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 sm:text-3xl">{!! number_format($this->collectionCount, 0, ',', '&nbsp;') !!}</span>
                    <span class="mt-1 text-sm text-gray-600 dark:text-gray-400">gyűjtemény</span>
                </a>
                <a href="{{ route('authors') }}" wire:navigate class="group flex flex-col items-center py-6 text-center hover:bg-gray-50 dark:hover:bg-zinc-800 transition">
                    <flux:icon name="users" class="mb-2 h-6 w-6 text-indigo-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition" />
                    <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 sm:text-3xl">{!! number_format($this->authorCount, 0, ',', '&nbsp;') !!}</span>
                    <span class="mt-1 text-sm text-gray-600 dark:text-gray-400">szerző</span>
                </a>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8 space-y-16">

        {{-- Collections Section --}}
        @if ($this->collections->isNotEmpty())
        <section>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <flux:heading size="xl" class="flex items-center gap-2">
                        <flux:icon name="folder" class="h-6 w-6 text-indigo-500" />
                        Gyűjtemények
                    </flux:heading>
                    <flux:subheading class="mt-1">Liturgikus énekeskönyvek és kottagyűjtemények</flux:subheading>
                </div>
                <a href="{{ route('collections') }}" wire:navigate class="shrink-0">
                    <flux:button variant="ghost" icon-trailing="arrow-right" size="sm">
                        Összes gyűjtemény
                    </flux:button>
                </a>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->collections as $collection)
                    <a href="{{ route('collection-view', $collection) }}" wire:navigate
                       class="group block rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/80 dark:hover:border-indigo-600">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/50">
                                <flux:icon name="folder" class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-gray-900 group-hover:text-indigo-600 dark:text-gray-100 dark:group-hover:text-indigo-400 truncate">
                                    {{ $collection->title }}
                                </div>
                                @if ($collection->abbreviation)
                                    <div class="mt-0.5 text-xs font-mono text-gray-500 dark:text-gray-400">{{ $collection->abbreviation }}</div>
                                @endif
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">
                                        {{ $collection->music_count }} zenemű
                                    </span>
                                    @foreach ($collection->genres as $genre)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-zinc-700 dark:text-gray-300">
                                            <flux:icon name="{{ $genre->icon() }}" variant="mini" class="h-3 w-3" />
                                            {{ $genre->label() }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
        @endif

        {{-- Recent Music Section --}}
        @if ($this->recentMusics->isNotEmpty())
        <section>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <flux:heading size="xl" class="flex items-center gap-2">
                        <flux:icon name="music" class="h-6 w-6 text-indigo-500" />
                        Legújabb zeneművek
                    </flux:heading>
                    <flux:subheading class="mt-1">Nemrégiben hozzáadott liturgikus énekek és zeneművek</flux:subheading>
                </div>
                <a href="{{ route('musics') }}" wire:navigate class="shrink-0">
                    <flux:button variant="ghost" icon-trailing="arrow-right" size="sm">
                        Összes zenemű
                    </flux:button>
                </a>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
                <div class="divide-y divide-gray-100 dark:divide-zinc-700/60">
                    @foreach ($this->recentMusics as $music)
                        <a href="{{ route('music-view', $music) }}" wire:navigate
                           class="group flex items-center gap-4 px-5 py-3.5 hover:bg-indigo-50/50 dark:hover:bg-zinc-700/40 transition bg-white dark:bg-zinc-800/80">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $music->is_verified ? 'bg-green-100 dark:bg-green-900/30' : 'bg-indigo-50 dark:bg-indigo-900/30' }}">
                                @if ($music->is_verified)
                                    <flux:icon name="check" variant="solid" class="h-4 w-4 text-green-500 dark:text-green-400" />
                                @else
                                    <flux:icon name="music" variant="mini" class="h-4 w-4 text-indigo-400" />
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-gray-900 group-hover:text-indigo-700 dark:text-gray-100 dark:group-hover:text-indigo-300 truncate">
                                    {{ $music->title }}
                                </div>
                                @if ($music->subtitle || $music->authors->isNotEmpty())
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 truncate">
                                        @if ($music->subtitle){{ $music->subtitle }}@endif
                                        @if ($music->subtitle && $music->authors->isNotEmpty()) · @endif
                                        @if ($music->authors->isNotEmpty()){{ $music->authors->pluck('name')->join(', ') }}@endif
                                    </div>
                                @endif
                            </div>
                            @if ($firstCollection = $music->collections->first())
                            @can('view', $firstCollection)
                                <span class="hidden shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 sm:inline dark:bg-zinc-700 dark:text-gray-400">
                                    {{ $firstCollection->abbreviation ?? $firstCollection->title }}
                                </span>
                            @endif
                            @endcan
                            <flux:icon name="chevron-right" variant="mini" class="h-4 w-4 shrink-0 text-gray-300 group-hover:text-indigo-400 dark:text-gray-600 transition" />
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        {{-- Feature cards row: Authors + Search CTA --}}
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            {{-- Authors card --}}
            <a href="{{ route('authors') }}" wire:navigate
               class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-indigo-300 hover:shadow-md transition dark:border-zinc-700 dark:bg-zinc-800/80 dark:hover:border-indigo-600">
                <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-indigo-50 dark:bg-indigo-900/20 group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/40 transition"></div>
                <div class="relative">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/50">
                        <flux:icon name="users" class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <flux:heading size="lg" class="mt-4 group-hover:text-indigo-700! dark:group-hover:text-indigo-300!">Szerzők</flux:heading>
                    <flux:subheading class="mt-1">
                        Zeneszerzők, szövegírók, gyűjtemény-szerkesztők adatai.
                    </flux:subheading>
                    <div class="mt-4 flex items-center gap-1 text-sm font-medium text-indigo-600 dark:text-indigo-400">
                        Szerzők böngészése
                        <flux:icon name="arrow-right" variant="mini" class="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                    </div>
                </div>
            </a>

            {{-- Search CTA card --}}
            <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-indigo-600 to-purple-700 p-6 shadow-sm dark:from-indigo-800 dark:to-purple-900">
                <div class="absolute -right-6 -bottom-6 h-24 w-24 rounded-full bg-white/10 pointer-events-none"></div>
                <div class="relative">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20">
                        <flux:icon name="magnifying-glass" class="h-6 w-6 text-white" />
                    </div>
                    <flux:heading size="lg" class="mt-4 text-white!">Keresés az adatbázisban</flux:heading>
                    <p class="mt-1 text-sm text-indigo-200">
                        Cím, szöveg, gyűjtemény vagy szerző alapján keresd meg a liturgiába illő éneket.
                    </p>
                    <div class="mt-4">
                        <a href="{{ route('musics') }}" wire:navigate>
                            <flux:button size="sm" class="bg-white! text-indigo-700! hover:bg-indigo-50! font-semibold">
                                Keresés indítása
                            </flux:button>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @guest
        {{-- Login nudge for guests --}}
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/30">
            <div class="flex items-start gap-4">
                <flux:icon name="information-circle" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div>
                    <flux:heading size="sm" class="text-amber-800! dark:text-amber-300!">Bejelentkezés szükséges a szerkesztéshez</flux:heading>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
                        A zeneművek, gyűjtemények és szerzők adatbázisának szerkesztéséhez, saját énekrendek készítéséhez, vagy hibajelzéshez kérjük, jelentkezz be vagy regisztrálj!
                    </p>
                    <div class="mt-3 flex items-center gap-2">
                        <a href="{{ route('login') }}">
                            <flux:button size="sm" variant="primary">Bejelentkezés</flux:button>
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}">
                                <flux:button size="sm" variant="ghost">Regisztráció</flux:button>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endguest

    </div>
</div>
