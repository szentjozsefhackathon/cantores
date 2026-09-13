@props(['plan', 'compact' => false])

{{-- The way from a plan to the projection thrown from it.

     The sibling of music-plan-booklet-actions, and it behaves the same way: a
     plan already projected once — the usual case for a parish that keeps a
     screen — offers those decks before the button that would start another. Only
     the reader's own: someone else's deck for a public plan is none of their
     business.

     The compact form is for the plan cards, whose toolbar column has room for
     the square icon button but not for its label. --}}
@auth
    @php
        $projections = $plan->projections()->mine()->latest('updated_at')->get();
        $openLabel = 'Vetítés megnyitása';
        $createLabel = $projections->isEmpty() ? 'Vetítés készítése' : 'Új vetítés';
    @endphp

    @if($projections->count() === 1)
        <flux:button variant="outline" color="purple" icon="presentation" :square="$compact" :title="$openLabel"
            href="{{ route('projections.edit', $projections->first()) }}">{{ $compact ? "" : $openLabel }}</flux:button>
    @elseif($projections->count() > 1)
        <flux:dropdown>
            <flux:button variant="outline" color="purple" icon="presentation" :square="$compact" :title="$openLabel"
                icon-trailing="{{ $compact ? '' : 'chevron-down' }}">{{ $compact ? "" : $openLabel }}</flux:button>
            <flux:menu>
                @foreach($projections as $projection)
                    <flux:menu.item :href="route('projections.edit', $projection)">{{ $projection->title }}</flux:menu.item>
                @endforeach
            </flux:menu>
        </flux:dropdown>
    @endif

    <form method="POST" action="{{ route('projections.store') }}" class="inline">
        @csrf
        <input type="hidden" name="music_plan_id" value="{{ $plan->id }}">
        <flux:button type="submit" variant="outline" color="purple" :square="$compact" :title="$createLabel"
            icon="presentation-plus">{{ $compact ? "" : $createLabel }}</flux:button>
    </form>
@endauth
