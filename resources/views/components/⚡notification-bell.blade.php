<?php

use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public int $unreadCount = 0;

    public function mount(NotificationService $notificationService): void
    {
        $user = Auth::user();
        if ($user) {
            $this->unreadCount = $notificationService->getUnreadCount($user);
        }
    }

    #[On('notification-created')]
    public function incrementCount(): void
    {
        $this->unreadCount++;
    }

    #[On('notifications-read')]
    public function resetCount(): void
    {
        $this->unreadCount = 0;
    }
}
?>

<div>
    <button
        type="button"
        class="relative p-2 text-gray-700 hover:text-gray-900 focus:outline-none"
        wire:click="$dispatch('open-notifications')"
        aria-label="Notifications"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full px-2 py-1 text-xs min-w-[1.5rem] h-6 flex items-center justify-center">
                {{ $unreadCount }}
            </span>
        @endif
    </button>
</div>