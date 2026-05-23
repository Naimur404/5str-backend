<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Queueable database notification used by all model observers and admin
 * notifications. Implementing ShouldQueue moves the persistence write off
 * the request thread; the response payload is unchanged because the
 * notification row is still produced by toDatabase().
 */
class DatabaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Persist on the dedicated `notifications` Horizon queue so a flood of
     * admin notifications never blocks higher-priority jobs.
     *
     * IMPORTANT (PHP 8.4): the `$queue` property is declared by
     * `Illuminate\Bus\Queueable` (untyped, no default). Under PHP 8.4
     * trait property declarations must match the host class **exactly**
     * — types and default values both. Redeclaring `public $queue
     * = 'notifications'` here therefore raises a fatal "Property
     * declaration is incompatible" error at class composition time
     * (the entire request errors with HTTP 500 the first time any
     * notifiable model is observed).
     *
     * The fix is to set the queue name in the constructor via
     * `onQueue()` instead of redeclaring the property.
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $icon = 'heroicon-o-bell',
        public readonly string $color = 'primary',
        public readonly ?array $actions = null,
        public readonly ?string $url = null,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'icon' => $this->icon,
            'color' => $this->color,
            'actions' => $this->actions,
            'url' => $this->url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'icon' => $this->icon,
            'color' => $this->color,
        ];
    }
}
