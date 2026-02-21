<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <flux:card class="p-5">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <div class="flex items-center gap-4">
                    <flux:icon name="musical-note" class="h-10 w-10 text-blue-600 dark:text-blue-400" variant="outline" />
                    <div>
                        <flux:heading size="xl">Énekrendjeim</flux:heading>
                        <flux:text class="text-neutral-600 dark:text-neutral-400">
                            Itt találod az összes létrehozott énekrendedet
                        </flux:text>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <form action="{{ route('music-plans.store') }}" method="POST" class="inline">
                        @csrf
                        <flux:button
                            type="submit"
                            variant="primary"
                            icon="plus">
                            Új énekrend
                        </flux:button>
                    </form>
                </div>
            </div>

            <!-- Search and filters -->
            <div class="mb-6">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <flux:field>
                            <flux:label>Keresés ünnepek között</flux:label>
                            <flux:input
                                type="search"
                                wire:model.live="search"
                                placeholder="Keresés ünnep neve, időszak vagy liturgikus év szerint..."
                                icon="magnifying-glass" />
                        </flux:field>
                    </div>
                    <div class="flex items-end">
                        @php
                            $totalPlans = $celebrations->sum(function ($celebration) {
                                return $celebration->musicPlans->count();
                            });
                        @endphp
                        <flux:badge color="blue" size="lg" class="px-4 py-2">
                            <flux:icon name="musical-note" class="h-4 w-4 mr-2" variant="mini" />
                            {{ $totalPlans }} énekrend {{ $celebrations->count() }} ünnepen
                        </flux:badge>
                    </div>
                </div>
            </div>

            <!-- Celebrations grouped view -->
            @if($celebrations->isEmpty())
                <flux:callout variant="secondary" icon="musical-note" class="border-dashed">
                    <flux:callout.heading>Nincs a keresésnek megfelelő énekrend</flux:callout.heading>
                    <x-slot name="actions">
                        <flux:button href="{{ route('dashboard') }}" variant="outline" size="sm">
                            Liturgikus naptár
                        </flux:button>
                    </x-slot>
                </flux:callout>
            @else
                <div class="space-y-8">
                    @foreach($celebrations as $celebration)
                        <div class="border border-neutral-200 dark:border-neutral-800 rounded-lg overflow-hidden">
                            <!-- Celebration header -->
                            <div class="bg-neutral-50 dark:bg-neutral-900 px-6 py-4 border-b border-neutral-200 dark:border-neutral-800">
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-3">
                                            <flux:icon name="calendar" class="h-5 w-5 text-blue-600 dark:text-blue-400" variant="outline" />
                                            <flux:heading size="lg">{{ $celebration->name }}</flux:heading>
                                        </div>
                                        <div class="mt-2 flex flex-wrap items-center gap-4 text-sm text-neutral-600 dark:text-neutral-400">
                                            <div class="flex items-center gap-1">
                                                <flux:icon name="clock" class="h-4 w-4" variant="mini" />
                                                {{ $celebration->actual_date->format('Y. m. d.') }}
                                            </div>
                                            @if($celebration->season_text)
                                                <div class="flex items-center gap-1">
                                                    <flux:icon name="leaf" class="h-4 w-4" variant="mini" />
                                                    {{ $celebration->season_text }}
                                                </div>
                                            @endif
                                            @if($celebration->year_letter)
                                                <div class="flex items-center gap-1">
                                                    <flux:icon name="book-open" class="h-4 w-4" variant="mini" />
                                                    {{ $celebration->year_letter }} év
                                                </div>
                                            @endif
                                            @if($celebration->is_custom)
                                                <flux:badge color="amber" size="sm">Egyéni ünnep</flux:badge>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <flux:badge color="blue" size="lg">
                                            {{ $celebration->musicPlans->count() }} énekrend
                                        </flux:badge>
                                    </div>
                                </div>
                            </div>

                            <!-- Music plans for this celebration -->
                            <div class="p-6">
                                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                                    @foreach($celebration->musicPlans as $plan)
                                        <livewire:music-plan-card :musicPlan="$plan" :key="$plan->id" />
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>
</div>