<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class TestNotification extends Notification
{
    use Queueable;

    public string $title;
    public string $message;
    public string $priority;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $title = 'Test Notification', string $message = 'This is a test notification', string $priority = 'medium')
    {
        $this->title = $title;
        $this->message = $message;
        $this->priority = $priority;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'priority' => $this->priority,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->message,
            'color' => $this->priority === 'high' ? 'danger' : ($this->priority === 'medium' ? 'warning' : 'info'),
            'icon' => 'heroicon-o-bell',
            'iconColor' => $this->priority === 'high' ? 'danger' : ($this->priority === 'medium' ? 'warning' : 'info'),
            'actions' => [],
        ];
    }
}
