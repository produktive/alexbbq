<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TemperatureAlertNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
    ) {}

    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->icon('/favicon.svg')
            ->body($this->body)
            ->action('View Cook', 'view_cook', $this->url)
            ->data(['url' => $this->url]);
    }
}
