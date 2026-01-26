<?php

namespace App\Events;

use App\Models\Duel;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OpponentAnswered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Duel $duel,
        public User $user
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('duel.' . $this->duel->room_code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'duel.opponent_answered';
    }

    public function broadcastWith(): array
    {
        return [
            'duel_id' => $this->duel->id,
            'user_id' => $this->user->id,
            'answered_at' => now()->toISOString(),
        ];
    }
}
