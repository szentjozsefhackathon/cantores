<?php

namespace App\Livewire\Pages;

use App\Facades\GenreContext;
use App\Models\Collection;
use App\Models\Genre;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public string $title = '';

    public ?string $abbreviation = null;

    public ?string $author = null;

    public array $selectedGenres = [];

    public bool $isPrivate = false;

    public function rendering(View $view): void
    {
        $layout = Auth::check() ? 'layouts::app' : 'layouts::app.main';
        $view->layout($layout);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function genres(): \Illuminate\Support\Collection
    {
        return Genre::allCached();
    }

    public function create(): void
    {
        $this->authorize('create', Collection::class);
        $this->title = '';
        $this->abbreviation = null;
        $this->author = null;
        $this->isPrivate = false;
        $this->selectedGenres = [];
        $genreId = GenreContext::getId();
        if ($genreId) {
            $this->selectedGenres = [$genreId];
        }
        $this->modal('create-collection')->show();
    }

    public function store(): void
    {
        $this->authorize('create', Collection::class);

        $validated = $this->validate([
            'title'            => ['required', 'string', 'max:255', Rule::unique('collections', 'title')],
            'abbreviation'     => ['nullable', 'string', 'max:20', Rule::unique('collections', 'abbreviation')],
            'author'           => ['nullable', 'string', 'max:255'],
            'isPrivate'        => ['boolean'],
            'selectedGenres'   => ['nullable', 'array'],
            'selectedGenres.*' => ['integer', Rule::exists('genres', 'id')],
        ]);

        $collection = Collection::create([
            ...$validated,
            'user_id'    => Auth::id(),
            'is_private' => $validated['isPrivate'] ?? false,
        ]);

        $collection->genres()->sync($validated['selectedGenres'] ?? []);

        $this->modal('create-collection')->close();
        $this->dispatch('collection-created');
    }

    #[Computed]
    public function collectionCount(): int
    {
        return Collection::public()->count();
    }

    #[Computed]
    public function totalMusicCount(): int
    {
        return \App\Models\Music::public()
            ->whereHas('collections', fn ($q) => $q->public())
            ->count();
    }

    #[Computed]
    public function collections()
    {
        return Collection::public()
            ->with('genres')
            ->withCount('music')
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->orderByDesc('music_count')
            ->paginate(12);
    }
}
?>

<div>
    {{-- Hero Section --}}
    <div class="relative overflow-hidden bg-linear-to-br from-emerald-600 via-teal-700 to-cyan-800 dark:from-emerald-900 dark:via-teal-900 dark:to-cyan-950">
        <div class="relative mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/20">
                        <flux:icon name="folder" class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white sm:text-2xl">Liturgikus gyűjtemények</h1>
                        <p class="text-sm text-emerald-200">Énekeskönyvek, kottagyűjtemények és dallamtárak</p>
                    </div>
                </div>
                <div class="hidden sm:flex items-center gap-2 shrink-0">
                    <a href="{{ route('musics') }}" wire:navigate>
                        <flux:button size="sm" variant="ghost" icon="music" class="border-white/30! text-white! hover:bg-white/10!">
                            Zeneművek
                        </flux:button>
                    </a>
                    <a href="{{ route('music-database') }}" wire:navigate>
                        <flux:button size="sm" variant="ghost" icon="home" class="border-white/30! text-white! hover:bg-white/10!">
                            Főoldal
                        </flux:button>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Bar --}}
    <div class="border-b border-gray-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 divide-x divide-gray-200 dark:divide-zinc-700">
                <div class="flex flex-col items-center py-6 text-center">
                    <flux:icon name="folder" class="mb-2 h-6 w-6 text-emerald-400" />
                    <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 sm:text-3xl">{!! number_format($this->collectionCount, 0, ',', '&nbsp;') !!}</span>
                    <span class="mt-1 text-sm text-gray-600 dark:text-gray-400">nyilvános gyűjtemény</span>
                </div>
                <div class="flex flex-col items-center py-6 text-center">
                    <flux:icon name="music" class="mb-2 h-6 w-6 text-emerald-400" />
                    <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 sm:text-3xl">{!! number_format($this->totalMusicCount, 0, ',', '&nbsp;') !!}</span>
                    <span class="mt-1 text-sm text-gray-600 dark:text-gray-400">zenemű gyűjteményekben</span>
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8 space-y-8">

        {{-- Search --}}
        <div class="flex items-center gap-3">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    icon="magnifying-glass"
                    placeholder="Keresés cím, rövidítés vagy szerkesztő szerint…"
                    class="w-full"
                />
            </div>
            @auth
                <flux:button wire:click="create" variant="primary" icon="plus" class="bg-emerald-600! hover:bg-emerald-700! shrink-0">
                    Új gyűjtemény
                </flux:button>
            @endauth
        </div>

        {{-- Collections Grid --}}
        @if ($this->collections->isNotEmpty())
            <section>
                <div class="flex items-center justify-between mb-4">
                    <flux:subheading>
                        @if ($this->search)
                            {{ $this->collections->total() }} találat a keresésre
                        @else
                            Rendezve: legtöbb zenemű szerint
                        @endif
                    </flux:subheading>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->collections as $collection)
                        <a href="{{ route('collection-view', $collection) }}" wire:navigate
                           class="group block rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/80 dark:hover:border-emerald-600">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/50">
                                    <flux:icon name="folder" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-gray-900 group-hover:text-emerald-600 dark:text-gray-100 dark:group-hover:text-emerald-400 truncate">
                                        {{ $collection->title }}
                                    </div>
                                    @if ($collection->abbreviation)
                                        <div class="mt-0.5 text-xs font-mono text-gray-500 dark:text-gray-400">{{ $collection->abbreviation }}</div>
                                    @endif
                                    @if ($collection->author)
                                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 truncate">{{ $collection->author }}</div>
                                    @endif
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">
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
                                <flux:icon name="chevron-right" variant="mini" class="h-4 w-4 shrink-0 text-gray-300 group-hover:text-emerald-400 dark:text-gray-600 transition mt-0.5" />
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $this->collections->links() }}
                </div>
            </section>
        @else
            <flux:callout variant="secondary" icon="folder">
                @if ($this->search)
                    Nincs találat a keresésre. Próbálj más kifejezést!
                @else
                    Még nincsenek nyilvános gyűjtemények.
                @endif
            </flux:callout>
        @endif

        {{-- Bottom CTA row --}}
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            {{-- Musics card --}}
            <a href="{{ route('musics') }}" wire:navigate
               class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-emerald-300 hover:shadow-md transition dark:border-zinc-700 dark:bg-zinc-800/80 dark:hover:border-emerald-600">
                <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-emerald-50 dark:bg-emerald-900/20 group-hover:bg-emerald-100 dark:group-hover:bg-emerald-900/40 transition"></div>
                <div class="relative">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/50">
                        <flux:icon name="music" class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <flux:heading size="lg" class="mt-4 group-hover:text-emerald-700! dark:group-hover:text-emerald-300!">Zeneművek</flux:heading>
                    <flux:subheading class="mt-1">
                        Kereshető adatbázis liturgikus énekekből és zeneművekből.
                    </flux:subheading>
                    <div class="mt-4 flex items-center gap-1 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                        Zeneművek böngészése
                        <flux:icon name="arrow-right" variant="mini" class="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                    </div>
                </div>
            </a>

            @auth
                {{-- Create collection CTA (authenticated) --}}
                <button wire:click="create"
                   class="group relative overflow-hidden rounded-2xl bg-linear-to-br from-emerald-600 to-teal-700 p-6 shadow-sm dark:from-emerald-800 dark:to-teal-900 hover:opacity-95 transition text-left w-full">
                    <div class="absolute -right-6 -bottom-6 h-24 w-24 rounded-full bg-white/10 pointer-events-none"></div>
                    <div class="relative">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20">
                            <flux:icon name="plus" class="h-6 w-6 text-white" />
                        </div>
                        <flux:heading size="lg" class="mt-4 text-white!">Új gyűjtemény létrehozása</flux:heading>
                        <p class="mt-1 text-sm text-emerald-200">
                            Hozd létre saját énekeskönyved vagy gyűjteményed a közös adatbázisban.
                        </p>
                        <div class="mt-4 flex items-center gap-1 text-sm font-semibold text-white">
                            Gyűjtemény létrehozása
                            <flux:icon name="arrow-right" variant="mini" class="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                        </div>
                    </div>
                </button>
            @else
                {{-- Login nudge (guest) --}}
                <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-emerald-600 to-teal-700 p-6 shadow-sm dark:from-emerald-800 dark:to-teal-900">
                    <div class="absolute -right-6 -bottom-6 h-24 w-24 rounded-full bg-white/10 pointer-events-none"></div>
                    <div class="relative">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20">
                            <flux:icon name="user-plus" class="h-6 w-6 text-white" />
                        </div>
                        <flux:heading size="lg" class="mt-4 text-white!">Közreműködsz?</flux:heading>
                        <p class="mt-1 text-sm text-emerald-200">
                            Saját gyűjtemény létrehozásához és szerkesztéséhez kérjük, jelentkezz be!
                        </p>
                        <div class="mt-4 flex items-center gap-2">
                            <a href="{{ route('login') }}">
                                <flux:button size="sm" class="bg-white! text-emerald-700! hover:bg-emerald-50! font-semibold">
                                    Bejelentkezés
                                </flux:button>
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}">
                                    <flux:button size="sm" variant="ghost" class="border-white/30! text-white! hover:bg-white/10!">
                                        Regisztráció
                                    </flux:button>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endauth
        </div>

    </div>

    {{-- Create Collection Modal --}}
    <flux:modal name="create-collection" max-width="md">
        <flux:heading size="lg">{{ __('Create Collection') }}</flux:heading>

        <div class="mt-2 space-y-4">
            <flux:field required>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:input
                    wire:model="title"
                    :placeholder="__('Enter collection title')"
                    autofocus
                />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Abbreviation') }}</flux:label>
                <flux:description>{{ __('Optional short form, e.g., ÉE, BWV') }}</flux:description>
                <flux:input
                    wire:model="abbreviation"
                    :placeholder="__('Enter abbreviation')"
                    maxlength="20"
                />
                <flux:error name="abbreviation" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Author') }}</flux:label>
                <flux:description>{{ __('Optional author or publisher') }}</flux:description>
                <flux:input
                    wire:model="author"
                    :placeholder="__('Enter author name')"
                />
                <flux:error name="author" />
            </flux:field>

            <flux:field>
                <flux:checkbox
                    wire:model="isPrivate"
                    :label="__('Make this collection private (only visible to you)')"
                />
                <flux:description>{{ __('Private collections are only visible to you and cannot be seen by other users.') }}</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Genres') }}</flux:label>
                <flux:description>{{ __('Select which genres this collection belongs to.') }}</flux:description>
                <div class="space-y-2">
                    @foreach($this->genres() as $genre)
                        <flux:checkbox
                            wire:model="selectedGenres"
                            value="{{ $genre->id }}"
                            :label="$genre->label()"
                        />
                    @endforeach
                </div>
                <flux:error name="selectedGenres" />
            </flux:field>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="store">
                {{ __('Create') }}
            </flux:button>
        </div>
    </flux:modal>
</div>
