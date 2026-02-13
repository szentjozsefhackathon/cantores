@php
    // Accept Realm model, realm name, or realm ID
    $realm = null;
    
    if ($setting instanceof \App\Models\Realm) {
        $realm = $setting;
    } elseif (is_string($setting)) {
        $realm = \App\Models\Realm::where('name', $setting)->first();
    } elseif (is_int($setting)) {
        $realm = \App\Models\Realm::find($setting);
    }
    
    $icon = $realm?->icon() ?? 'other';
@endphp

@if($icon === 'organist')
    <x-gameicon-pipe-organ class="h-10 w-10 text-zinc-600 dark:text-zinc-600" />
@elseif($icon === 'guitar')
    <flux:icon name="guitar" class="h-10 w-10" />
@else
    <flux:icon name="musical-note" class="h-10 w-10" variant="outline" />
@endif
