{{-- One row of the booklet, wherever in the plan it stands.

     Every row is a component of its own, so that opening one row's toolbar — or
     ticking one of its boxes — costs the server that row rather than the whole
     plan. Livewire leaves a component it has already drawn alone when the page
     around it is drawn again, which is exactly what a list of thirty scores needs
     and what this one used to spend a second of every change ignoring. --}}
@php $source = $entry->isText() ? null : $this->entrySources->get($entry->score_id); @endphp

<livewire:booklet.entry-row
    :entry="$entry"
    :score-url="$source['url'] ?? null"
    :incipit-url="$source['incipit_url'] ?? null"
    :writing="$this->openedTextId === $entry->id"
    :key="'entry-'.$entry->id"
/>
