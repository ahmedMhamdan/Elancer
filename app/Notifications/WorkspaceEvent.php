<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Stores the event kind rather than a sentence, so the bell renders it in the
 * recipient's current language. Title and actor are member-entered text.
 */
class WorkspaceEvent extends Notification
{
    public function __construct(private string $kind, private string $href, private ?string $title = null, private ?string $actor = null, public readonly ?int $conversation = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'href' => $this->href, 'title' => $this->title, 'actor' => $this->actor, 'conversation' => $this->conversation];
    }
}
