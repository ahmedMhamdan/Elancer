<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Tells one member's open pages that something of theirs changed. It carries
 * identifiers only; the page then asks the server for what that member may see.
 */
class WorkspaceSignal implements ShouldBroadcastNow
{
    public function __construct(private int $user, public ?string $notification = null, public ?int $conversation = null) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('workspace.'.$this->user);
    }

    public function broadcastAs(): string
    {
        return 'signal';
    }

    /** A live update is a convenience: an unreachable broadcaster is reported, never allowed to fail the member's action. */
    public static function send(int $user, ?string $notification = null, ?int $conversation = null): void
    {
        rescue(fn () => event(new self($user, $notification, $conversation)));
    }
}
