@php
    $iconOrSetting = \App\MusicPlanSetting::tryFrom($setting)?->icon() ?? $setting;
@endphp

@if($iconOrSetting === 'organist')
    <x-gameicon-pipe-organ class="h-10 w-10 text-zinc-600 dark:text-zinc-600" />
@elseif($iconOrSetting === 'guitar')
    <flux:icon name="guitar" class="h-10 w-10" />
@else
    <flux:icon name="musical-note" class="h-10 w-10" variant="outline" />
@endif
