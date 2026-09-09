@props(['plan'])

{{-- The way from a plan to the booklet printed from it.

     A plan may already have been printed — the usual case once a service has
     been prepared — so the booklets made from it are offered before the button
     that would start yet another one. Only the reader's own booklets: someone
     else's copy of a public plan is none of their business. --}}
@auth
    @php
        $booklets = $plan->booklets()->mine()->latest('updated_at')->get();
    @endphp

    @if($booklets->count() === 1)
        <flux:button variant="outline" color="blue" icon="book-open" href="{{ route('booklets.edit', $booklets->first()) }}">
            Füzet megnyitása
        </flux:button>
    @elseif($booklets->count() > 1)
        <flux:dropdown>
            <flux:button variant="outline" color="blue" icon="book-open" icon-trailing="chevron-down">
                Füzet megnyitása
            </flux:button>
            <flux:menu>
                @foreach($booklets as $booklet)
                    <flux:menu.item :href="route('booklets.edit', $booklet)">{{ $booklet->title }}</flux:menu.item>
                @endforeach
            </flux:menu>
        </flux:dropdown>
    @endif

    <form method="POST" action="{{ route('booklets.store') }}" class="inline">
        @csrf
        <input type="hidden" name="music_plan_id" value="{{ $plan->id }}">
        <flux:button type="submit" variant="outline" color="blue" icon="{{ $booklets->isEmpty() ? 'book-open' : 'plus' }}">
            {{ $booklets->isEmpty() ? 'Füzet készítése' : 'Új füzet' }}
        </flux:button>
    </form>
@endauth
