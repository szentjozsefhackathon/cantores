<?php

namespace App\Livewire\Pages;

use App\Models\Author;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
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

    #[Url(as: 'sort')]
    public string $sort = 'music_count';

    public string $name = '';

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

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Author::class);
        $this->name = '';
        $this->isPrivate = false;
        $this->modal('create-author')->show();
    }

    public function store(): void
    {
        $this->authorize('create', Author::class);

        $validated = $this->validate([
            'name'      => $this->getNameValidationRule(),
            'isPrivate' => ['boolean'],
        ]);

        Author::create([
            'name'       => $validated['name'],
            'user_id'    => Auth::id(),
            'is_private' => $validated['isPrivate'] ?? false,
        ]);

        $this->modal('create-author')->close();
        $this->dispatch('author-created');
    }

    private function getNameValidationRule(): array
    {
        $rules = ['required', 'string', 'max:255'];

        $rules[] = function ($_, $value, $fail) {
            if ($this->isPrivate === false) {
                $exists = Author::where('name', $value)
                    ->where('is_private', false)
                    ->exists();

                if ($exists) {
                    $fail(__('An author with this name already exists in the public library.'));
                }
            }
        };

        return $rules;
    }

    #[Computed]
    public function authorCount(): int
    {
        return Author::public()->count();
    }

    #[Computed]
    public function totalMusicCount(): int
    {
        return \App\Models\Music::public()
            ->whereHas('authors', fn ($q) => $q->public())
            ->count();
    }

    #[Computed]
    public function authors()
    {
        return Author::public()
            ->withCount('music')
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->sort === 'name', fn ($q) => $q->orderBy('name'))
            ->when($this->sort !== 'name', fn ($q) => $q->orderByDesc('music_count'))
            ->paginate(12);
    }
}
?>

<div>
    {{-- Hero Section --}}
    <div class="relative overflow-hidden bg-linear-to-br from-indigo-600 via-violet-700 to-purple-800 dark:from-indigo-900 dark:via-violet-900 dark:to-purple-950">
        <div class="relative mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/20">
                        <flux:icon name="users" class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white sm:text-2xl">Szerzők</h1>
                        <p class="text-sm text-indigo-200">Zeneszerzők, szövegírók és más alkotók</p>
                    </div>
                </div>
                <div class="hidden sm:flex items-center gap-2 shrink-0">
                    <a href="{{ route('collections') }}" wire:navigate>
                        <flux:button size="sm" variant="ghost" icon="folder" class="border-white/30! text-white! hover:bg-white/10!">
                            Gyűjtemények
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
                    <flux:icon name="users" class="mb-2 h-6 w-6 text-indigo-400" />
                    <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 sm:text-3xl">{!! number_format($this->authorCount, 0, ',', '&nbsp;') !!}</span>
                    <span class="mt-1 text-sm text-gray-600 dark:text-gray-400">nyilvános szerző</span>
                </div>
                <div class="flex flex-col items-center py-6 text-center">
                    <flux:icon name="music" class="mb-2 h-6 w-6 text-indigo-400" />
                    <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 sm:text-3xl">{!! number_format($this->totalMusicCount, 0, ',', '&nbsp;') !!}</span>
                    <span class="mt-1 text-sm text-gray-600 dark:text-gray-400">zenemű szerzővel</span>
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
                    placeholder="Keresés szerző neve szerint…"
                    class="w-full"
                />
            </div>
            @auth
                <flux:button wire:click="create" variant="primary" icon="plus" class="bg-indigo-600! hover:bg-indigo-700! shrink-0">
                    Új szerző
                </flux:button>
            @endauth
        </div>

        {{-- Authors Grid --}}
        @if ($this->authors->isNotEmpty())
            <section>
                <div class="flex items-center justify-between mb-4">
                    <flux:subheading>
                        @if ($this->search)
                            {{ $this->authors->total() }} találat a keresésre
                        @elseif ($this->sort === 'name')
                            Rendezve: névsor szerint
                        @else
                            Rendezve: legtöbb zenemű szerint
                        @endif
                    </flux:subheading>
                    <div class="flex items-center gap-1">
                        <flux:button
                            wire:click="$set('sort', 'music_count')"
                            size="xs"
                            :variant="$this->sort === 'music_count' ? 'primary' : 'ghost'"
                            icon="musical-note"
                        >
                            Zeneművek
                        </flux:button>
                        <flux:button
                            wire:click="$set('sort', 'name')"
                            size="xs"
                            :variant="$this->sort === 'name' ? 'primary' : 'ghost'"
                            icon="bars-arrow-up"
                        >
                            A–Z
                        </flux:button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->authors as $author)
                        <a href="{{ route('author-view', $author) }}" wire:navigate
                           class="group block rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/80 dark:hover:border-indigo-600 min-h-24">
                            <div class="flex items-start gap-3 h-full">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/50">
                                    <flux:icon name="user" class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900 group-hover:text-indigo-600 dark:text-gray-100 dark:group-hover:text-indigo-400 line-clamp-2">
                                        {{ $author->name }}
                                    </div>
                                    <div class="mt-2">
                                        <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                            {{ $author->music_count }} zenemű
                                        </span>
                                    </div>
                                </div>
                                <flux:icon name="chevron-right" variant="mini" class="h-4 w-4 shrink-0 text-gray-300 group-hover:text-indigo-400 dark:text-gray-600 transition mt-0.5" />
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $this->authors->links() }}
                </div>
            </section>
        @else
            <flux:callout variant="secondary" icon="users">
                @if ($this->search)
                    Nincs találat a keresésre. Próbálj más kifejezést!
                @else
                    Még nincsenek nyilvános szerzők.
                @endif
            </flux:callout>
        @endif

        {{-- Bottom CTA row --}}
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            {{-- Collections card --}}
            <a href="{{ route('collections') }}" wire:navigate
               class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hover:border-indigo-300 hover:shadow-md transition dark:border-zinc-700 dark:bg-zinc-800/80 dark:hover:border-indigo-600">
                <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-indigo-50 dark:bg-indigo-900/20 group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/40 transition"></div>
                <div class="relative">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/50">
                        <flux:icon name="folder" class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <flux:heading size="lg" class="mt-4 group-hover:text-indigo-700! dark:group-hover:text-indigo-300!">Gyűjtemények</flux:heading>
                    <flux:subheading class="mt-1">
                        Énekeskönyvek, kottagyűjtemények és dallamtárak böngészése.
                    </flux:subheading>
                    <div class="mt-4 flex items-center gap-1 text-sm font-medium text-indigo-600 dark:text-indigo-400">
                        Gyűjtemények böngészése
                        <flux:icon name="arrow-right" variant="mini" class="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                    </div>
                </div>
            </a>

            @auth
                {{-- Create author CTA (authenticated) --}}
                <button wire:click="create"
                   class="group relative overflow-hidden rounded-2xl bg-linear-to-br from-indigo-600 to-violet-700 p-6 shadow-sm dark:from-indigo-800 dark:to-violet-900 hover:opacity-95 transition text-left w-full">
                    <div class="absolute -right-6 -bottom-6 h-24 w-24 rounded-full bg-white/10 pointer-events-none"></div>
                    <div class="relative">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20">
                            <flux:icon name="plus" class="h-6 w-6 text-white" />
                        </div>
                        <flux:heading size="lg" class="mt-4 text-white!">Új szerző létrehozása</flux:heading>
                        <p class="mt-1 text-sm text-indigo-200">
                            Adj hozzá új szerzőt a közös zenei adatbázishoz.
                        </p>
                        <div class="mt-4 flex items-center gap-1 text-sm font-semibold text-white">
                            Szerző létrehozása
                            <flux:icon name="arrow-right" variant="mini" class="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                        </div>
                    </div>
                </button>
            @else
                {{-- Login nudge (guest) --}}
                <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-indigo-600 to-violet-700 p-6 shadow-sm dark:from-indigo-800 dark:to-violet-900">
                    <div class="absolute -right-6 -bottom-6 h-24 w-24 rounded-full bg-white/10 pointer-events-none"></div>
                    <div class="relative">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20">
                            <flux:icon name="user-plus" class="h-6 w-6 text-white" />
                        </div>
                        <flux:heading size="lg" class="mt-4 text-white!">Közreműködsz?</flux:heading>
                        <p class="mt-1 text-sm text-indigo-200">
                            Új szerző hozzáadásához és szerkesztéséhez kérjük, jelentkezz be!
                        </p>
                        <div class="mt-4 flex items-center gap-2">
                            <a href="{{ route('login') }}">
                                <flux:button size="sm" class="bg-white! text-indigo-700! hover:bg-indigo-50! font-semibold">
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

    {{-- Create Author Modal --}}
    <flux:modal name="create-author" max-width="md">
        <flux:heading size="lg">{{ __('Create Author') }}</flux:heading>

        <div class="mt-2 space-y-4">
            <flux:field required>
                <flux:label>{{ __('Use Last Name, First Name format for Non-Hungarian authors (e.g., Bach, Johann Sebastian).') }}</flux:label>
                <flux:input
                    wire:model="name"
                    :placeholder="__('Enter author name')"
                    autofocus
                    autocomplete="off"
                />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:checkbox
                    wire:model="isPrivate"
                    :label="__('Make this author private (only visible to you)')"
                />
                <flux:description>{{ __('Private authors are only visible to you and cannot be seen by other users.') }}</flux:description>
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
