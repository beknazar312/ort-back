<?php

namespace App\Events;

use App\Models\Duel;
use App\Models\DuelQuestion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewQuestion implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Duel $duel,
        public DuelQuestion $duelQuestion
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('duel.' . $this->duel->room_code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'duel.new_question';
    }

    public function broadcastWith(): array
    {
        return [
            'duel_id' => $this->duel->id,
            'question_index' => $this->duelQuestion->question_order,
            'challenger_lives' => $this->duel->challenger_lives,
            'opponent_lives' => $this->duel->opponent_lives,
            'question' => [
                'id' => $this->duelQuestion->id,
                'text' => $this->duelQuestion->question->text,
                'answers' => $this->duelQuestion->question->answers->map(fn($a) => [
                    'id' => $a->id,
                    'text' => $a->text,
                ])->toArray(),
            ],
            'started_at' => $this->duelQuestion->started_at->toISOString(),
        ];
    }
}
