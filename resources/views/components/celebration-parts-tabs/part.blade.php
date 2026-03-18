@props(['part'])

@php
    $sanitize = fn (string $text): string => strip_tags($text, '<b><br><sup><small><i><em><strong>');
    $fullText = $sanitize($part['text'] ?? '');
    $truncated = mb_strlen($fullText) > 100 ? mb_substr($fullText, 0, 100) . '...' : $fullText;
    $needsExpand = mb_strlen($fullText) > 100;
@endphp

<div class="rounded-2xl border border-zinc-200 bg-amber-50/80 dark:bg-zinc-900 p-4 dark:border-zinc-800 font-serif shadow-lg">
    <div class="grid grid-cols-1 gap-2 text-sm">
        <div>
            <flux:heading class="inline">{{ $part['ref'] ?? '' }}</flux:heading>
            <flux:text class="inline">{!! $sanitize($part['teaser'] ?? '') !!}</flux:text>
        </div>
        <div>{{ $part['title'] ?? '' }}</div>
        <div x-data="{ expanded: false }" class="text-sm">
            <span x-show="!expanded">{!! $truncated !!}</span>
            <span x-show="expanded" style="display: none;">{!! $fullText !!}</span>
            @if ($needsExpand)
                <button
                    @click="expanded = !expanded"
                    class="ml-2 text-blue-600 dark:text-blue-400 hover:underline text-xs font-medium"
                >
                    <span x-show="!expanded">{{ __('Bővebben') }}</span>
                    <span x-show="expanded" style="display: none;">{{ __('Összecsukás') }}</span>
                </button>
            @endif
        </div>
        <div>{{ $part['ending'] ?? '' }}</div>
    </div>
</div>
