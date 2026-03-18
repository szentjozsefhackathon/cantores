@props(['parts', 'wireModel', 'tabPrefix'])

<x-mary-tabs wire:model="{{ $wireModel }}">
    @foreach ($parts as $partIndex => $part)
        @if (isset($part[0]) && is_array($part[0]))
            @foreach ($part as $subIndex => $subPart)
                @php $subLabel = ($subPart['short_title'] ?? 'Rész') . (isset($subPart['cause']) ? ' (' . $subPart['cause'] . ')' : ''); @endphp
                <x-mary-tab name="{{ $tabPrefix }}-{{ $partIndex }}-{{ $subIndex }}" label="{{ $subLabel }}">
                    <x-celebration-parts-tabs.part :part="$subPart" />
                </x-mary-tab>
            @endforeach
        @else
            <x-mary-tab name="{{ $tabPrefix }}-{{ $partIndex }}" label="{{ $part['short_title'] ?? 'Rész ' . ($partIndex + 1) }}">
                <x-celebration-parts-tabs.part :part="$part" />
            </x-mary-tab>
        @endif
    @endforeach
</x-mary-tabs>
