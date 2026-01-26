<?php

namespace App\Events;

use App\Models\Duel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DuelDeclined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Duel $duel
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->duel->challenger_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'duel.declined';
    }

    public function broadcastWith(): array
    {
        $opponent = $this->duel->opponent;

        return [
            'id' => $this->duel->id,
            'opponent' => [
                'id' => $opponent->id,
                'name' => $opponent->name,
                'first_name' => $opponent->first_name,
            ],
        ];
    }
}
