<x-layouts::app :title="__('Dashboard')">
    <div class="grid grid-cols-1 md:grid-cols-2 h-full w-full flex-1 gap-4 rounded-xl">
        <div class="col-span-1">
            <livewire:liturgical-info />
        </div>
        <div class="col-span-1">
            <!-- Right half: Add content or leave empty -->
        </div>
    </div>
</x-layouts::app>