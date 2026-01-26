<?php

namespace App\Events;

use App\Models\Duel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DuelStarted implements ShouldBroadcastNow
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
        return 'duel.started';
    }

    public function broadcastWith(): array
    {
        $currentQuestion = $this->duel->currentQuestion();

        return [
            'duel_id' => $this->duel->id,
            'room_code' => $this->duel->room_code,
            'started_at' => $this->duel->started_at->toISOString(),
            'challenger_lives' => $this->duel->challenger_lives,
            'opponent_lives' => $this->duel->opponent_lives,
            'current_question_index' => $this->duel->current_question_index,
            'question' => $currentQuestion ? [
                'id' => $currentQuestion->id,
                'text' => $currentQuestion->question->text,
                'answers' => $currentQuestion->question->answers->map(fn($a) => [
                    'id' => $a->id,
                    'text' => $a->text,
                ])->toArray(),
                'started_at' => $currentQuestion->started_at?->toISOString(),
            ] : null,
        ];
    }
}
