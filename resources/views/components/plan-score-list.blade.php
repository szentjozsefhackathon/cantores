@props(['scores' => []])

{{--
    The scores behind one music in a plan, as this reader may see them.

    Live references rather than downloaded PDFs: what the list is read for, before
    a service, is whether the arrangement has moved and whether it still opens.
--}}
<div class="mt-2 space-y-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
    @foreach($scores as $planScore)
        <div class="flex flex-wrap items-center gap-1.5 text-sm" wire:key="plan-score-{{ $planScore['id'] }}">
            @if($planScore['url'])
                <a href="{{ $planScore['url'] }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                    {{ $planScore['title'] }}
                </a>
            @else
                <span class="font-medium">{{ $planScore['title'] }}</span>
            @endif

            <flux:badge size="sm" color="zinc">{{ $planScore['format'] }}</flux:badge>

            @if($planScore['is_borrowed'] && $planScore['owner_name'])
                <flux:badge size="sm" color="amber" icon="arrow-path-rounded-square">
                    {{ __('On loan · :name\'s score', ['name' => $planScore['owner_name']]) }}
                </flux:badge>
            @endif

            @if($planScore['expires_at'])
                <flux:badge size="sm" color="amber" icon="clock">
                    {{ __('Until :date', ['date' => $planScore['expires_at']->translatedFormat('Y-m-d')]) }}
                </flux:badge>
            @endif

            @if($planScore['changed_at'])
                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Last changed') }}: {{ $planScore['changed_at']->translatedFormat('Y-m-d') }}
                </span>
            @endif
        </div>
    @endforeach
</div>
