@php
    // Accept Realm model, realm name, or realm ID   
    if (is_string($realm)) {
        $realm = \App\Models\Realm::where('name', $realm)->first();
    } elseif (is_int($realm)) {
        $realm = \App\Models\Realm::find($realm);
    } else if ($realm instanceof \App\Models\Realm) {
        // Do nothing, $realm is already a Realm instance
    } else {
        $realm = null;
    }
    
    $icon = $realm?->icon() ?? 'other';
@endphp

@if($icon === 'organist')
    <x-gameicon-pipe-organ class="h-10 w-10 text-zinc-600 dark:text-zinc-600" />
@elseif($icon === 'guitar')
    <flux:icon name="guitar" class="h-10 w-10" />
@elseif($icon === 'other')
    <flux:icon name="guitarist" />
@else
    <flux:icon name="musical-note" class="h-10 w-10" variant="outline" />
@endif
