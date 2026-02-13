<?php

use App\Models\Realm;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public ?int $selectedRealmId = null;

    public function mount(): void
    {
        $this->selectedRealmId = Auth::user()->current_realm_id;
    }

    public function realms()
    {
        return Realm::all();
    }

    public function updatedSelectedRealmId($value): void
    {
        if (! $value) {
            return;
        }

        $user = Auth::user();
        $user->current_realm_id = $value;
        $user->save();

        // Dispatch event to notify other components
        $this->dispatch('realm-changed', realmId: $value);
    }
}
?>

<div class="realm-selector">
    <flux:select
        wire:model.live="selectedRealmId"
        placeholder="{{ __('Select a realm') }}"
        class="w-full"
    >
        <option value="">{{ __('Select a realm') }}</option>
        @foreach($this->realms() as $realm)
            <option value="{{ $realm->id }}" @selected($selectedRealmId == $realm->id)>
                {{ $realm->label() }}
            </option>
        @endforeach
    </flux:select>
</div>