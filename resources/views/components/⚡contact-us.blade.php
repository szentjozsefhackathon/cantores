<?php

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $showModal = false;

    public string $subject = '';

    public string $message = '';

    #[On('openContactModal')]
    public function openModal(): void
    {
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset('subject', 'message');
    }

    public function submit(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            $this->dispatch('contact-failed', message: __('You must be logged in to send a contact message.'));

            return;
        }

        /** @var NotificationService $notificationService */
        $notificationService = app(NotificationService::class);
        $notificationService->createContactMessage($user, $this->subject, $this->message);

        $this->dispatch('contact-success', message: __('Your message has been sent successfully.'));
        $this->closeModal();
    }

    public function render(): View
    {
        return view('components.⚡contact-us');
    }
};
?>
<div>
    <x-action-message on="contact-success" />
    <x-action-message on="contact-failed" />

    <!-- Modal -->
    @if($showModal)
    <flux:modal wire:model="showModal" max-width="md">
        <flux:heading size="lg">{{ __('Contact Us') }}</flux:heading>
        <flux:subheading>
            {{ __('Send us a message. We will get back to you as soon as possible.') }}
        </flux:subheading>

        <div class="mt-6 space-y-4">
            <flux:field required>
                <flux:label>{{ __('Subject') }}</flux:label>
                <flux:input
                    wire:model.live.debounce.250ms="subject"
                    :placeholder="__('Enter a brief subject...')"
                    maxlength="100" />
            </flux:field>

            <flux:field required>
                <flux:label>{{ __('Message') }}</flux:label>
                <flux:description>{{ __('Maximum 500 characters.') }}</flux:description>
                <flux:textarea
                    wire:model.live.debounce.250ms="message"
                    :placeholder="__('Enter your message...')"
                    rows="5"
                    maxlength="500" />
            </flux:field>
        </div>
        <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400 mt-1">
            <flux:error name="message" />
            <span>{{ strlen($message) }}/500</span>
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
                {{ __('Send Message') }}
            </flux:button>
        </div>
    </flux:modal>
    @endif
</div>