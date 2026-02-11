@php
    $iconOrSetting = \App\MusicPlanSetting::tryFrom($setting)?->icon() ?? $setting;
@endphp

@if($iconOrSetting === 'organist')
    <x-gameicon-pipe-organ class="inline-block h-10 w-10 mr-1 align-text-bottom" />
@else
    {{ $iconOrSetting }}
@endif
