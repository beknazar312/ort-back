<?php

namespace App\Events;

use App\Models\Duel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DuelEnded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Duel $duel
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('duel.' . $this->duel->room_code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'duel.ended';
    }

    public function broadcastWith(): array
    {
        $winner = $this->duel->winner;

        return [
            'duel_id' => $this->duel->id,
            'status' => $this->duel->status,
            'winner_id' => $this->duel->winner_id,
            'winner' => $winner ? [
                'id' => $winner->id,
                'name' => $winner->name,
                'first_name' => $winner->first_name,
            ] : null,
            'surrendered_by' => $this->duel->surrendered_by,
            'challenger_lives' => $this->duel->challenger_lives,
            'opponent_lives' => $this->duel->opponent_lives,
            'completed_at' => $this->duel->completed_at->toISOString(),
        ];
    }
}
