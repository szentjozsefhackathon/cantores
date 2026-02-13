<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

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
                    <flux:button 
                        href="{{ route('dashboard') }}" 
                        variant="outline" 
                        icon="arrow-left">
                        Vissza
                    </flux:button>
                    <flux:button
                        href="{{ route('music-plan-editor') }}"
                        variant="primary"
                        icon="plus">
                        Új énekrend
                    </flux:button>
                </div>
            </div>

            <!-- Search and filters -->
            <div class="mb-6">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <flux:field>
                            <flux:label>Keresés énekrendek között</flux:label>
                            <flux:input 
                                type="search" 
                                wire:model.live="search" 
                                placeholder="Keresés ünnep neve, időszak vagy liturgikus év szerint..." 
                                icon="magnifying-glass" />
                        </flux:field>
                    </div>
                    <div class="flex items-end">
                        <flux:badge color="blue" size="lg" class="px-4 py-2">
                            <flux:icon name="musical-note" class="h-4 w-4 mr-2" variant="mini" />
                            {{ $musicPlans->total() }} énekrend
                        </flux:badge>
                    </div>
                </div>
            </div>

            <!-- Music plans grid -->
            @if($musicPlans->isEmpty())
                <flux:callout variant="secondary" icon="musical-note" class="border-dashed">
                    <flux:callout.heading>Még nincsenek énekrendjeid</flux:callout.heading>
                    <flux:callout.text>
                        Hozz létre első énekrended a liturgikus naptárból vagy az "Új énekrend" gombbal.
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button href="{{ route('dashboard') }}" variant="outline" size="sm">
                            Liturgikus naptár
                        </flux:button>
                    </x-slot>
                </flux:callout>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                    @foreach($musicPlans as $plan)
                        <livewire:music-plan-card :musicPlan="$plan" :key="$plan->id" />
                    @endforeach
                </div>

                <!-- Pagination -->
                @if($musicPlans->hasPages())
                    <div class="mt-8 pt-6 border-t border-neutral-200 dark:border-neutral-800">
                        {{ $musicPlans->links() }}
                    </div>
                @endif
            @endif
        </flux:card>
    </div>
</div>