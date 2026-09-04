<?php

use Illuminate\Support\Facades\Blade;

it('renders the incipit image with a magnifier overlay and a full-image lightbox', function (): void {
    $html = Blade::render(
        '<x-incipit-image :src="$src" :alt="$alt" img-class="block max-h-14" />',
        ['src' => 'https://example.test/incipits/42.png', 'alt' => 'Kyrie']
    );

    expect($html)
        ->toContain('https://example.test/incipits/42.png')
        ->toContain('aria-label="Kép megtekintése teljes méretben"')
        ->toContain('incipitZoom = true')
        ->toContain('x-teleport="body"')
        ->toContain('block max-h-14');

    expect(substr_count($html, 'https://example.test/incipits/42.png'))->toBe(2);
});

it('anchors the zoom over the original incipit instead of a full-window overlay', function (): void {
    $html = Blade::render(
        '<x-incipit-image :src="$src" :alt="$alt" img-class="block max-h-14" />',
        ['src' => 'https://example.test/incipits/42.png', 'alt' => 'Kyrie']
    );

    expect($html)
        ->toContain('x-ref="incipitTrigger"')
        ->toContain('positionZoom()')
        ->toContain(':style="zoomStyle"')
        ->toContain(':class="zoomStyle ? \'opacity-100\' : \'opacity-0\'"')
        ->not->toContain('bg-black/80');
});

it('stops the zoom button from triggering an enclosing wire:navigate link', function (): void {
    $html = Blade::render(
        '<x-incipit-image :src="$src" :alt="$alt" img-class="block max-h-14" />',
        ['src' => 'https://example.test/incipits/42.png', 'alt' => 'Kyrie']
    );

    expect($html)
        ->toContain('x-on:mousedown.prevent.stop')
        ->toContain('x-on:mouseup.stop');
});

it('applies passed attribute classes to the wrapper, not the image', function (): void {
    $html = Blade::render(
        '<x-incipit-image :src="$src" class="hidden sm:inline-block" img-class="h-10 object-contain" />',
        ['src' => 'https://example.test/incipits/7.png']
    );

    expect($html)
        ->toContain('relative inline-block group/incipit hidden sm:inline-block')
        ->toContain('h-10 object-contain');
});
