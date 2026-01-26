<?php

namespace App\Events;

use App\Models\Friendship;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendRequestReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Friendship $friendship
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->friendship->friend_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'friend.request.received';
    }

    public function broadcastWith(): array
    {
        $sender = $this->friendship->user;

        return [
            'id' => $this->friendship->id,
            'user' => [
                'id' => $sender->id,
                'name' => $sender->name,
                'first_name' => $sender->first_name,
                'last_name' => $sender->last_name,
                'username' => $sender->username,
                'photo_url' => $sender->photo_url,
            ],
            'created_at' => $this->friendship->created_at->toISOString(),
        ];
    }
}
