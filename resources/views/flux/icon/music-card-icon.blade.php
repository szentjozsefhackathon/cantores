@blaze

{{-- Credit: Lucide (https://lucide.dev) --}}

@props([
    'variant' => 'outline',
])

@php
if ($variant === 'solid') {
    throw new \Exception('The "solid" variant is not supported in Lucide.');
}

$classes = Flux::classes('shrink-0')
    ->add(match($variant) {
        'outline' => '[:where(&)]:size-6',
        'solid' => '[:where(&)]:size-6',
        'mini' => '[:where(&)]:size-5',
        'micro' => '[:where(&)]:size-4',
    });

$strokeWidth = match ($variant) {
    'outline' => 2,
    'mini' => 2.25,
    'micro' => 2.5,
};
@endphp

<svg
    {{ $attributes->class($classes) }}
    data-flux-icon
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $strokeWidth }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    data-slot="icon"
>
  <path d="M16 10h2" />
  <path d="M16 14h2" />
  <!-- Music note -->
  <ellipse cx="6" cy="15" rx="1.5" ry="2" fill="currentColor" />
  <path d="M7.5 15V6" />
  <ellipse cx="11" cy="17" rx="1.5" ry="2" fill="currentColor" />
  <path d="M12.5 17V8" />
  <path d="M7.5 6Q9.5 7 12.5 8" />
  <rect x="1" y="2" width="22" height="20" rx="2" />
</svg>
