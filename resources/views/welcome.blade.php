<x-layouts::app.main>

    <div class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
            <main class="flex w-full flex-col-reverse lg:max-w-4xl lg:flex-row">
                <div class="w-full">
                    <livewire:liturgical-info />
                </div>
            </main>
        </div>

        <div class="w-full lg:max-w-4xl mx-auto mt-8 flex justify-center">
            <livewire:realm-selector />
        </div>

        @if (Route::has('login'))
            <div class="h-14.5 hidden lg:block"></div>
        @endif
</x-layouts::app.main>