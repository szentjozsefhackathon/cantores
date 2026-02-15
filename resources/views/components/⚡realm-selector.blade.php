<?php

use App\Facades\RealmContext;
use App\Models\Realm;
use Livewire\Component;

new class extends Component
{
    public ?int $selectedRealmId = null;

    public function mount(): void
    {
        $this->selectedRealmId = RealmContext::getId();
    }

    public function realms()
    {
        return Realm::all();
    }

    public function updatedSelectedRealmId($value): void
    {
        // Convert empty string to null
        if ($value === '') {
            $value = null;
        }

        RealmContext::set($value);

        // Dispatch event to notify other components
        $this->dispatch('realm-changed', realmId: $value);
    }
}
?>

<div class="flex items-center justify-center">
    <flux:radio.group wire:model.live="selectedRealmId" variant="segmented">
            @if (is_null($this->selectedRealmId))
                <flux:radio label="Mind" value="" checked />
            @else
                <flux:radio label="Mind" value="" />
            @endif
            @foreach($this->realms() as $realm)
                <flux:radio value="{{ $realm->id }}" icon="{{ $realm->icon() }}" />
            @endforeach
        </flux:radio.group>
</div>
