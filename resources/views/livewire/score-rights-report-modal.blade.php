<div class="inline">
    <flux:button size="xs" variant="ghost" icon="flag" wire:click="openModal">
        {{ __('Report a rights problem') }}
    </flux:button>

    @if($showModal)
    <flux:modal wire:model="showModal" max-width="lg" x-on:close="$wire.closeModal()">
        <flux:heading size="lg">{{ __('Report a rights problem') }}</flux:heading>
        <flux:subheading>
            {{ __('About :title. You do not need an account, and the report goes straight to our editors.', ['title' => $score->title]) }}
        </flux:subheading>

        @if($filedReportId)
        <div class="mt-6 space-y-3 rounded-lg border border-emerald-300 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/40">
            <flux:heading size="sm">{{ __('Report :reference recorded.', ['reference' => '#'.$filedReportId]) }}</flux:heading>
            <flux:text class="text-sm">
                {{ __('A human editor will look at it. If the objection holds up, the score leaves the public library while it is examined, and we will write to you at :email.', ['email' => $reporterEmail]) }}
            </flux:text>
        </div>

        <div class="mt-6 flex justify-end">
            <flux:button variant="primary" wire:click="closeModal">{{ __('Close') }}</flux:button>
        </div>
        @else
        <div class="mt-6 space-y-4">
            <flux:field required>
                <flux:label>{{ __('In what capacity are you writing?') }}</flux:label>
                <flux:select wire:model="capacity">
                    <flux:select.option value="">{{ __('Choose one…') }}</flux:select.option>
                    @foreach($this->capacities() as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="capacity" />
            </flux:field>

            <flux:field required>
                <flux:label>{{ __('What is the problem?') }}</flux:label>
                <flux:description>
                    {{ __('Which right you hold, and why you think this score infringes it. Naming the edition or the publisher helps us decide quickly.') }}
                </flux:description>
                <flux:textarea wire:model="claim" rows="5" maxlength="2000" />
                <flux:error name="claim" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field required>
                    <flux:label>{{ __('Your name') }}</flux:label>
                    <flux:input wire:model="reporterName" maxlength="120" />
                    <flux:error name="reporterName" />
                </flux:field>

                <flux:field required>
                    <flux:label>{{ __('Email we can answer on') }}</flux:label>
                    <flux:input type="email" wire:model="reporterEmail" maxlength="180" />
                    <flux:error name="reporterEmail" />
                </flux:field>
            </div>

            <flux:text class="text-xs text-zinc-500">
                {{ __('We keep your name and address for handling the report and for our record of the decision.') }}
                <a href="{{ route('score-rights') }}" target="_blank" class="underline">{{ __('What happens next') }}</a>
            </flux:text>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="closeModal">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="submit" wire:loading.attr="disabled">
                {{ __('Send report') }}
            </flux:button>
        </div>
        @endif
    </flux:modal>
    @endif
</div>
