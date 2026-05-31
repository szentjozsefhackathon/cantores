<?php

namespace App\Livewire\Pages;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notifications')]
class Notifications extends Component
{
    use WithPagination;

    /**
     * Reply bodies keyed by notification id.
     *
     * @var array<int, string>
     */
    #[Validate(['replyBodies.*' => ['nullable', 'string', 'max:2000']])]
    public array $replyBodies = [];

    public function mount(): void
    {
        //
    }

    public function reply(int $notificationId, NotificationService $notificationService): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        $body = trim($this->replyBodies[$notificationId] ?? '');

        if ($body === '') {
            throw ValidationException::withMessages([
                "replyBodies.{$notificationId}" => __('The reply cannot be empty.'),
            ]);
        }

        $notification = Notification::find($notificationId);

        if (! $notification) {
            return;
        }

        if (! $notification->canBeRepliedBy($user)) {
            abort(403);
        }

        $notificationService->reply($notification, $user, $body);

        unset($this->replyBodies[$notificationId]);
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

        return view('pages.notifications', [
            'notifications' => $notifications,
        ]);
    }
}
