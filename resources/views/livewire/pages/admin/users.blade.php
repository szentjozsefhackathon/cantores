<x-pages::admin.layout :heading="__('Users')">
    <div class="mt-5">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('ID') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Nickname') }}</flux:table.column>
                <flux:table.column>{{ __('Email Verified') }}</flux:table.column>
                <flux:table.column>{{ __('Created At') }}</flux:table.column>
                <flux:table.column>{{ __('Updated At') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($users as $user)
                    <flux:table.row>
                        <flux:table.cell>{{ $user->id }}</flux:table.cell>
                        <flux:table.cell>{{ $user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>{{ $user->nickname ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $user->email_verified_at ? $user->email_verified_at->format('Y-m-d H:i') : __('Not verified') }}</flux:table.cell>
                        <flux:table.cell>{{ $user->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $user->updated_at->format('Y-m-d H:i') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center">{{ __('No users found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</x-pages::admin.layout>