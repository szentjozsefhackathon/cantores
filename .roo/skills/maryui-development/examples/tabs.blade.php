<x-tabs wire:model="selectedTab">
    <x-tab name="users-tab" label="Users" icon="o-users">
        <div>Users</div>
    </x-tab>
    <x-tab name="tricks-tab" label="Tricks" icon="o-sparkles">
        <div>Tricks</div>
    </x-tab>
    <x-tab name="musics-tab" label="Musics" icon="o-musical-note">
        <div>Musics</div>
    </x-tab>
</x-tabs>

<x-button label="Change to Musics" @click="$wire.selectedTab = 'musics-tab'" />