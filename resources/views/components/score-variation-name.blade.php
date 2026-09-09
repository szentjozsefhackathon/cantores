@props(['score'])

{{-- What this score is called among the other versions of the same music —
     "Fuvola", "Kórus". Said beside the title rather than in place of it,
     because several rows of one music otherwise read as the same score. --}}
@if(trim((string) $score?->variation_name) !== '')
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400']) }}>
    <flux:icon name="layers" variant="micro" class="shrink-0" />
    {{ $score->variation_name }}
</span>
@endif
