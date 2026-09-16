<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChatMessageNotification extends Notification
{
    use Queueable;

    public function __construct(public int $conversationId, public string $senderName, public string $preview) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'chat_message',
            'conversation_id' => $this->conversationId,
            'sender_name' => $this->senderName,
            'preview' => $this->preview,
        ];
    }
}
