<?php

namespace App\Livewire\Pages;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notifications')]
class Notifications extends Component
{
    use WithPagination;

    public function mount(): void
    {
        //
    }

    public function markAsRead(string $notificationId, NotificationService $notificationService): void
    {
        $user = Auth::user();
        $notification = \App\Models\Notification::find($notificationId);
        if ($notification && $user) {
            $notificationService->markAsRead($notification, $user);
            $this->dispatch('notifications-read');
        }
    }

    public function markAllAsRead(NotificationService $notificationService): void
    {
        $user = Auth::user();
        if ($user) {
            $notificationService->markAllAsRead($user);
            $this->dispatch('notifications-read');
        }
    }

    public function render(NotificationService $notificationService)
    {
        $user = Auth::user();
        $notifications = $user ? $notificationService->getNotificationsForUser($user) : collect();

        return view('livewire.pages.notifications', [
            'notifications' => $notifications,
        ]);
    }
}
