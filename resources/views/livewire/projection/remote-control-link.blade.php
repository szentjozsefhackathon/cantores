<div>
    {{-- Only while a show is up, and not from the remote itself. --}}
    @if($this->presentation && ! request()->routeIs('projection-remote', 'projection-remote.*'))
        @if($only !== 'floating')
            <div class="lg:hidden">
                <flux:tooltip :content="__('A show is up — go to the remote.')">
                    <flux:button
                        :href="route('projection-remote')"
                        wire:navigate
                        variant="primary"
                        size="sm"
                        icon="presentation"
                        square
                        aria-label="{{ __('Remote control') }}" />
                </flux:tooltip>
            </div>
        @endif

        @if($only !== 'mobile')
            <div class="fixed top-4 right-4 z-50 hidden lg:block">
                <flux:tooltip :content="__('A show is up — go to the remote.')">
                    <flux:button
                        :href="route('projection-remote')"
                        wire:navigate
                        variant="primary"
                        icon="presentation"
                        class="shadow-lg">
                        {{ __('Remote control') }}
                    </flux:button>
                </flux:tooltip>
            </div>
        @endif
    @endif
</div>
