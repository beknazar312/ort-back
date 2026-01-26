<?php

namespace App\Events;

use App\Models\Duel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DuelInviteReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Duel $duel
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->duel->opponent_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'duel.invite.received';
    }

    public function broadcastWith(): array
    {
        $challenger = $this->duel->challenger;
        $subject = $this->duel->subject;

        return [
            'id' => $this->duel->id,
            'room_code' => $this->duel->room_code,
            'challenger' => [
                'id' => $challenger->id,
                'name' => $challenger->name,
                'first_name' => $challenger->first_name,
                'username' => $challenger->username,
                'photo_url' => $challenger->photo_url,
            ],
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'slug' => $subject->slug,
                'icon' => $subject->icon,
                'color' => $subject->color,
            ],
            'initial_lives' => $this->duel->initial_lives,
            'time_per_question' => $this->duel->time_per_question,
            'created_at' => $this->duel->created_at->toISOString(),
        ];
    }
}
