<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/** F48 — notifikasi dalam aplikasi. */
class SystemNotice extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }

    public static function sendTo(iterable $users, string $title, string $body, ?string $url = null): void
    {
        $recipients = collect($users)->filter();
        if ($recipients->isNotEmpty()) {
            NotificationFacade::send($recipients, new self($title, $body, $url));
        }
    }
}
