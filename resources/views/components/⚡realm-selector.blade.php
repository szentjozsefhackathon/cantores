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
        $user = Auth::user();

        // Convert empty string to null
        if ($value === '') {
            $value = null;
        }

        $user->current_realm_id = $value;
        $user->save();

        // Dispatch event to notify other components
        $this->dispatch('realm-changed', realmId: $value);
    }
}
?>

<div class="flex items-center justify-center">
    <flux:radio.group wire:model.live="selectedRealmId" variant="segmented" label="Műfaj">
            @if (is_null(Auth::user()->current_realm_id))
                <flux:radio label="Mind" value="" checked />
            @else
                <flux:radio label="Mind" value="" />
            @endif
            @foreach($this->realms() as $realm)
                <flux:radio value="{{ $realm->id }}" icon="{{ $realm->icon() }}" />
            @endforeach
        </flux:radio.group>    
</div>