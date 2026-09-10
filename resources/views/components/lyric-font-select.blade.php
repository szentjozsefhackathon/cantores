{{--
    The face a score's lyrics are set in.

    One component for all twelve of these selects — four formats, three views —
    because the list behind them is the list of faces the PDF exporter can embed,
    and a select that has drifted from it offers a font that comes out as a
    substitute in print. ABC is the odd one out in quoting: abc2svg writes the
    name into a %%vocalfont directive of its own, where the quotes would be part
    of the name.
--}}
@props([
    'model',
    'quoted' => true,
    'width' => 'w-40',
])

@php($quote = $quoted ? chr(39) : '')

<flux:select size="sm" x-model="{{ $model }}" class="{{ $width }} text-xs">
    @foreach(\App\Support\BookletSettingFields::selectableFonts() as $font)
        <flux:select.option :value="$quote.$font.$quote">{{ $font }}</flux:select.option>
    @endforeach
</flux:select>
