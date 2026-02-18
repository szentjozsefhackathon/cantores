@php
    // Accept Genre model, genre name, or genre ID   
    // Parameter may be called $genre or $genre (for backward compatibility)
    $genreParam = $genre ?? $genre ?? null;
    
    if (is_string($genreParam)) {
        $genre = \App\Models\Genre::where('name', $genreParam)->first();
    } elseif (is_int($genreParam)) {
        $genre = \App\Models\Genre::find($genreParam);
    } else if ($genreParam instanceof \App\Models\Genre) {
        $genre = $genreParam;
    } else {
        $genre = null;
    }
    
    $icon = $genre?->icon() ?? 'genre_other';
@endphp

@if($icon === 'organist')
    <flux:icon name="organist" class="h-10 w-10 dark:text-zinc-300 text-zinc-600" />
@elseif($icon === 'guitar')
    <flux:icon name="guitar" class="h-10 w-10" />
@elseif($icon === 'other')
    <flux:icon name="genre_other" />
@else
    <flux:icon name="musical-note" class="h-10 w-10" variant="outline" />
@endif
