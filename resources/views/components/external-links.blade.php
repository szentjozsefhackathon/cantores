@php($links = \App\Models\ExternalLink::allCached())

@if ($links->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'w-full']) }}>
        <flux:heading size="lg" class="mb-4">Más hasznos oldalak</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($links as $link)
                <a
                    href="{{ $link->url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="group flex flex-col rounded-xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:border-accent hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <div class="flex items-center gap-2">
                        <flux:icon name="arrow-top-right-on-square" variant="mini" class="shrink-0 text-accent" />
                        <flux:heading size="sm" class="group-hover:text-accent">{{ $link->title }}</flux:heading>
                    </div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $link->description }}</p>
                </a>
            @endforeach
        </div>
    </div>
@endif
