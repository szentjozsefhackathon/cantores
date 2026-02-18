<div>
    <!-- Modal -->
    <flux:modal wire:model="showModal" max-width="md">
        <flux:heading size="lg">{{ __('Edit User Roles') }}</flux:heading>

        <div class="mt-6 space-y-4">
            @if($user)
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <p><strong>{{ __('User') }}:</strong> {{ $user->name }} ({{ $user->email }})</p>
                    <p><strong>{{ __('Display Name') }}:</strong> {{ $user->display_name ?? '-' }}</p>
                </div>

                <flux:field>
                    <flux:label>{{ __('Roles') }}</flux:label>
                    <div class="space-y-2">
                        @foreach($availableRoles as $role)
                            <flux:checkbox
                                wire:model="selectedRoles"
                                value="{{ $role }}"
                                label="{{ ucfirst($role) }}"
                            />
                        @endforeach
                    </div>
                    <flux:error name="selectedRoles" />
                </flux:field>
            @else
                <p class="text-gray-500">{{ __('Loading user...') }}</p>
            @endif
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="closeModal" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:loading.class="opacity-50 cursor-not-allowed">
                {{ __('Save Changes') }}
            </flux:button>
        </div>
    </flux:modal>
</div>
