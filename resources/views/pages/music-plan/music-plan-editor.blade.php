<?php

use App\Models\MusicPlan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public MusicPlan $musicPlan;

    public function mount(MusicPlan $musicPlan): void
    {
        $this->authorize('view', $musicPlan);
        $this->musicPlan = $musicPlan;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->musicPlan);

        $this->musicPlan->delete();

        $this->redirectRoute('dashboard');
    }
};
?>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <flux:card class="p-6">
            <div class="flex items-center gap-4 mb-6">
                <flux:icon name="musical-note" class="h-8 w-8 text-blue-600" variant="outline" />
                <flux:heading size="xl">Énekrend szerkesztése</flux:heading>
            </div>

            <div class="space-y-6">
                <!-- Celebration info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">Ünnep neve</flux:heading>
                        <flux:text class="text-lg font-semibold">{{ $musicPlan->celebration_name }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">Dátum</flux:heading>
                        <flux:text class="text-lg font-semibold">{{ $musicPlan->actual_date->translatedFormat('Y. F j.') }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">Liturgikus év</flux:heading>
                        <flux:text class="text-lg font-semibold">{{ $musicPlan->year_letter ?? '–' }} {{ $musicPlan->year_parity ? '(' . $musicPlan->year_parity . ')' : '' }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">Hangszerek</flux:heading>
                        <flux:text class="text-lg font-semibold">{{ \App\MusicPlanSetting::tryFrom($musicPlan->setting)?->label() ?? $musicPlan->setting }}</flux:text>
                    </div>
                </div>

                <!-- Season and week -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">Időszak</flux:heading>
                        <flux:badge color="blue" size="lg">{{ $musicPlan->season }}</flux:badge>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">Hét</flux:heading>
                        <flux:badge color="green" size="lg">{{ $musicPlan->week }}</flux:badge>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">Nap</flux:heading>
                        <flux:badge color="purple" size="lg">{{ $musicPlan->day }}</flux:badge>
                    </div>
                </div>

                <!-- Readings code -->
                @if ($musicPlan->readings_code)
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">Olvasmánykód</flux:heading>
                    <flux:text class="font-mono bg-neutral-100 dark:bg-neutral-800 p-2 rounded">{{ $musicPlan->readings_code }}</flux:text>
                </div>
                @endif

                <!-- Status -->
                <div class="flex items-center justify-between pt-6 border-t border-neutral-200 dark:border-neutral-800">
                    <div class="flex items-center gap-3">
                        <flux:icon name="{{ $musicPlan->is_published ? 'eye' : 'eye-slash' }}" class="h-5 w-5 text-neutral-500" variant="mini" />
                        <flux:text class="font-medium">{{ $musicPlan->is_published ? 'Közzétéve' : 'Privát' }}</flux:text>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row gap-3 pt-6">
                    <flux:button variant="outline" color="zinc" icon="arrow-left" href="{{ route('dashboard') }}">
                        Vissza a vezérlőpulthoz
                    </flux:button>
                    <flux:button
                        variant="danger"
                        icon="trash"
                        wire:click="delete"
                        wire:confirm="Biztosan törölni szeretnéd ezt az énekrendet? A művelet nem visszavonható."
                    >
                        Énekrend törlése
                    </flux:button>
                </div>
            </div>
        </flux:card>
    </div>
</div>
