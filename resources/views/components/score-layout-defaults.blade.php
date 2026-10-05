@props(['isGuest' => false])

{{-- Which default the open layout is on, and the way back to either.

     A new score opens on its author's saved default and an existing one on what
     it was saved with, and the two can be close enough to look alike — so the
     toolbar says which it is showing, and resetting names which default it goes
     back to. Reads scoreEditor's layoutSource(), myDefaultBucket() and
     resetToDefaults(). --}}
<flux:tooltip :content="__('Which defaults this layout is on')">
    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
        x-bind:class="{
            'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-200': layoutSource() === 'mine',
            'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300': layoutSource() === 'factory',
            'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200': layoutSource() === 'custom',
        }"
        x-text="layoutSourceLabel()"
        data-layout-source></span>
</flux:tooltip>

<flux:dropdown>
    <flux:tooltip :content="__('Reset to defaults')">
        <flux:button icon="arrow-path" variant="ghost" />
    </flux:tooltip>

    <flux:menu>
        @if (! $isGuest)
            <flux:menu.item icon="bookmark" x-show="myDefaultBucket() !== null" x-on:click="resetToDefaults('mine')">
                {{ __('Reset to my default') }}
            </flux:menu.item>
        @endif
        <flux:menu.item icon="arrow-path" x-on:click="resetToDefaults('factory')">
            {{ __('Reset to factory default') }}
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>

@if (! $isGuest)
    <flux:tooltip :content="__('Save as my default for this ratio')">
        <flux:button icon="bookmark" variant="ghost" x-on:click="saveAsDefault()" />
    </flux:tooltip>
@endif
