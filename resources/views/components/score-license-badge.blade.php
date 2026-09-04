@props(['publication' => null])

@if($publication instanceof \App\Models\ScorePublication)
@php($license = $publication->effectiveLicense())
@if($license->deedUrl())
<a href="{{ $license->deedUrl() }}" rel="license noopener" target="_blank" class="shrink-0">
    <flux:badge color="green" size="sm" icon="lock-open">{{ $license->shortCode() }}</flux:badge>
</a>
@else
<flux:badge color="green" size="sm" icon="lock-open" class="shrink-0">{{ $license->shortCode() }}</flux:badge>
@endif
@endif
