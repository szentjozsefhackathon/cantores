<div>
    <x-action-message on="error-report-success" />
    <x-action-message on="error-report-failed" />

    <!-- Modal -->
    <flux:modal wire:model="showModal" max-width="md">
        <flux:heading size="lg">{{ __('Report Error') }}</flux:heading>
        <flux:subheading>
            {{ __('Please describe the error you found. This report will be sent to the owner and administrators.') }}
        </flux:subheading>

        <div class="mt-6 space-y-4 ">
            <flux:field required>
                <flux:label>{{ __('Error Description') }}</flux:label>
                <flux:description>{{ __('Maximum 160 characters.') }}</flux:description>
                <flux:textarea autofocus
                    wire:model.live.debounce.250ms="message"
                    :placeholder="__('Enter a brief description of the error...')"
                    rows="4"
                    maxlength="160" />
            </flux:field>
        </div>
        <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400 mt-1">
            <flux:error name="message" />
            <span>{{ strlen($message) }}/160</span>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button
                variant="ghost"
                wire:click="closeModal">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button
                variant="primary"
                wire:click="submit"
                wire:loading.attr="disabled">
                {{ __('Submit Report') }}
            </flux:button>
        </div>
    </flux:modal>
</div>