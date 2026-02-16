<x-mary-tabs wire:model="selectedTab">
    <x-mary-tab name="users-tab" label="Users" icon="o-users">
        <div>Users</div>
    </x-mary-tab>
    <x-mary-tab name="tricks-tab" label="Tricks" icon="o-sparkles">
        <div>Tricks</div>
    </x-mary-tab>
    <x-mary-tab name="musics-tab" label="Musics" icon="o-musical-note">
        <div>Musics</div>
    </x-mary-tab>
</x-mary-tabs>

<x-button label="Change to Musics" @click="$wire.selectedTab = 'musics-tab'" />