@props([
    'type' => null,
])

@if($type instanceof \App\AuthorType)
    <flux:icon
        :name="$type->icon()"
        variant="micro"
        class="text-gray-500 dark:text-gray-400"
        :title="$type->label()" />
@endif
