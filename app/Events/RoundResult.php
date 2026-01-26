<?php

namespace App\Events;

use App\Models\Duel;
use App\Models\DuelQuestion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoundResult implements ShouldBroadcastNow
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
        return 'duel.round_result';
    }

    public function broadcastWith(): array
    {
        $correctAnswer = $this->duelQuestion->question->answers->firstWhere('is_correct', true);

        return [
            'duel_id' => $this->duel->id,
            'question_order' => $this->duelQuestion->question_order,
            'round_result' => $this->duelQuestion->round_result,
            'challenger_answer_id' => $this->duelQuestion->challenger_answer_id,
            'opponent_answer_id' => $this->duelQuestion->opponent_answer_id,
            'challenger_is_correct' => $this->duelQuestion->challenger_is_correct,
            'opponent_is_correct' => $this->duelQuestion->opponent_is_correct,
            'correct_answer_id' => $correctAnswer?->id,
            'explanation' => $this->duelQuestion->question->explanation,
            'challenger_lives' => $this->duel->challenger_lives,
            'opponent_lives' => $this->duel->opponent_lives,
        ];
    }
}
