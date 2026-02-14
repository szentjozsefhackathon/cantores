<?php

use App\Models\Realm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

new class extends Component
{
    public ?int $selectedRealmId = null;

    public function mount(): void
    {
        if (Auth::check()) {
            $this->selectedRealmId = Auth::user()->current_realm_id;
        } else {
            $this->selectedRealmId = Session::get('current_realm_id');
        }
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

        if (Auth::check()) {
            $user = Auth::user();
            $user->current_realm_id = $value;
            $user->save();
        } else {
            Session::put('current_realm_id', $value);
        }

        // Dispatch event to notify other components
        $this->dispatch('realm-changed', realmId: $value);
    }
}
?>

<div class="flex items-center justify-center">
    <flux:radio.group wire:model.live="selectedRealmId" variant="segmented">
            <flux:radio label="Mind" value="" />
            @foreach($this->realms() as $realm)
                <flux:radio value="{{ $realm->id }}" icon="{{ $realm->icon() }}" />
            @endforeach
        </flux:radio.group>
</div>
