<?php

namespace App\Livewire\Components;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
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

    public function render()
    {
        return view('livewire.components.notification-bell');
    }
}
