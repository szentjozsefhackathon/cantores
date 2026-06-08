@props(['format' => null])

@if($format instanceof \App\Enums\ScoreFormat)
<flux:badge color="zinc" size="sm">{{ $format->label() }}</flux:badge>
@else
<flux:badge color="zinc" size="sm">{{ __('Links') }}</flux:badge>
@endif
