<div class="flex items-start max-md:flex-col">
<div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Admin') }}">
            <flux:navlist.item :href="route('admin.nickname-data')" wire:navigate :current="request()->routeIs('admin.nickname-data')">
                {{ __('Nickname and city master data') }}
            </flux:navlist.item>
            <flux:navlist.item :href="route('admin.users')" wire:navigate :current="request()->routeIs('admin.users')">
                {{ __('Users') }}
            </flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>
        <div class="mt-5 w-full">
            {{ $slot }}
            
        </div>
    </div>    
</div>
