@props(['tag'])

<div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
    <flux:icon :name="$tag->icon()" class="h-3 w-3" />
    <span>{{ $tag->name }}</span>
</div>
