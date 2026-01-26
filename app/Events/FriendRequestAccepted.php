<?php

namespace App\Events;

use App\Models\Friendship;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendRequestAccepted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Friendship $friendship
    ) {}

    public function broadcastOn(): array
    {
        // Notify the original requester that their request was accepted
        return [
            new PrivateChannel('user.' . $this->friendship->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'friend.request.accepted';
    }

    public function broadcastWith(): array
    {
        $accepter = $this->friendship->friend;

        return [
            'id' => $this->friendship->id,
            'user' => [
                'id' => $accepter->id,
                'name' => $accepter->name,
                'first_name' => $accepter->first_name,
                'last_name' => $accepter->last_name,
                'username' => $accepter->username,
                'photo_url' => $accepter->photo_url,
            ],
            'accepted_at' => $this->friendship->accepted_at->toISOString(),
        ];
    }
}
