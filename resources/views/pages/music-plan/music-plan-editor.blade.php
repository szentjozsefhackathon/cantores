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
        <flux:card class="p-5">
            <div class="flex items-center gap-4 mb-4">
                <flux:icon name="musical-note" class="h-7 w-7 text-blue-600" variant="outline" />
                <flux:heading size="xl">Énekrend szerkesztése</flux:heading>
            </div>

            <div class="space-y-4">
                <!-- Combined info grid -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Ünnep neve</flux:heading>
                        <flux:text class="text-base font-semibold">{{ $musicPlan->celebration_name }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Dátum</flux:heading>
                        <flux:text class="text-base font-semibold">{{ $musicPlan->actual_date->translatedFormat('Y. F j.') }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Liturgikus év</flux:heading>
                        <flux:text class="text-base font-semibold">{{ $musicPlan->year_letter ?? '–' }} {{ $musicPlan->year_parity ? '(' . $musicPlan->year_parity . ')' : '' }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Hangszerek</flux:heading>
                        <flux:text class="text-base font-semibold">{{ \App\MusicPlanSetting::tryFrom($musicPlan->setting)?->label() ?? $musicPlan->setting }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Időszak, hét, nap</flux:heading>
                        <div class="flex flex-row gap-2">
                            <flux:badge color="blue" size="sm">{{ $musicPlan->season_text }}</flux:badge>
                            <flux:badge color="green" size="sm">{{ $musicPlan->week }}.hét</flux:badge>
                            <flux:badge color="purple" size="sm">{{ $musicPlan->day_name }}</flux:badge>
                        </div>
                    </div>
                </div>


                <!-- Status -->
                <div class="flex items-center justify-between pt-4 border-t border-neutral-200 dark:border-neutral-800">
                    <div class="flex items-center gap-3">
                        <flux:icon name="{{ $musicPlan->is_published ? 'eye' : 'eye-slash' }}" class="h-5 w-5 text-neutral-500" variant="mini" />
                        <flux:text class="font-medium">{{ $musicPlan->is_published ? 'Közzétéve' : 'Privát' }}</flux:text>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row gap-3 pt-4">
                    <flux:button variant="outline" color="zinc" icon="arrow-left" href="{{ route('dashboard') }}">
                        Vissza az irányítópultra
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
