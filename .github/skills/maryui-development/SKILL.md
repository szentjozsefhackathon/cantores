---
name: maryui-development
description: "Use this skill for maryUI components (robsontenorio/mary v2) in this Livewire app. Trigger when a UI needs tabs or a searchable/multi-select picker (choices), which Flux UI Free lacks, or when working with <x-mary-*> tags. Prefer Flux for everything Flux Free covers; reach for maryUI where Flux's equivalent is Pro-only."
---

# maryUI Development

maryUI is a daisyUI-based Livewire component library. In this project Flux UI Free is the main kit. maryUI fills the gaps where the Flux component is Pro-only.

## Rules

- Every tag uses the `mary-` prefix (`config/mary.php`): `<x-mary-tabs>`, never `<x-tabs>`. Examples from the maryUI website leave the prefix out, so add it.
- Use maryUI for **tabs** and **choices** (search / multi-select). For buttons, inputs, selects, modals, icons and the rest, use Flux.
- Both components entangle a Livewire property, so they need `wire:model` and only work inside Livewire components.
- Look at an existing usage before you write a new one.

## Tabs

The selected tab is a Livewire string property. Each `name` is a value that property can take. An optional `icon` uses Heroicons (`o-users`) or `lucide.<name>`.

```blade
<x-mary-tabs wire:model="activeTab" class="mb-8">
    <x-mary-tab name="music" label="Music" icon="o-musical-note">
        ...
    </x-mary-tab>
    <x-mary-tab name="plans" label="Plans" icon="lucide.list-music">
        ...
    </x-mary-tab>
</x-mary-tabs>
```

- Switching to a tab from Alpine: `@click="$wire.activeTab = 'plans'"`.
- Several tab groups in one component need a distinct `wire:key` each.
- Alpine state such as `copied` or `$refs` declared in a wrapping `x-data` works inside the tabs.
- Examples in the project: `resources/views/components/celebration-parts-tabs.blade.php`, `resources/views/pages/music-plans.blade.php`, `resources/views/livewire/pages/psalm-tones.blade.php`.

## Choices

Options are arrays or objects with `id` and `name`, plus optional `avatar`. Change the keys with `option-value`, `option-label` and `option-avatar`. Add `single` for one value. Without it, the bound property holds an array.

```blade
{{-- Server-side search: searchable + search-function names a component method that refills the options --}}
<x-mary-choices
    wire:model="selectedAuthorId"
    :options="$authorsSearchable"
    option-avatar="avatarThumbUrl"
    search-function="searchAuthor"
    no-result-text="Nincs találat"
    single
    searchable />
```

Custom rendering uses `@scope` slots. A scope is a closure, so Flux components inside it need Blaze's runtime passed in as `$__blaze`:

```blade
<x-mary-choices wire:model.live="tagFilters" :options="$this->tagOptions">
    @scope('item', $option, $__blaze)
        <x-mary-list-item :item="$option">
            <x-slot:avatar>
                <flux:icon :name="$option['icon'] ?? null" class="h-4 w-4" />
            </x-slot:avatar>
        </x-mary-list-item>
    @endscope
    @scope('selection', $selected, $__blaze)
        ...
    @endscope
</x-mary-choices>
```

Examples in the project: `resources/views/partials/music-browser-filters.blade.php`, `resources/views/pages/editor/music-editor.blade.php`, `resources/views/components/music-plan-editor/⚡slot-plan/slot-plan.blade.php`.

## Other components

The installed package (`vendor/robsontenorio/mary/src/View/Components`) also ships `list-item`, `icon`, `progress`, `spotlight` (configured as `App\Support\Spotlight`) and more. Read the component class for its props before using one, and check first that Flux Free has no equivalent.
