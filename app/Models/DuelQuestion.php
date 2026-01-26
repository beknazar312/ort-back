<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuelQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'duel_id',
        'question_id',
        'question_order',
        'challenger_answer_id',
        'opponent_answer_id',
        'challenger_is_correct',
        'opponent_is_correct',
        'challenger_answered_at',
        'opponent_answered_at',
        'round_result',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'challenger_is_correct' => 'boolean',
            'opponent_is_correct' => 'boolean',
            'challenger_answered_at' => 'datetime',
            'opponent_answered_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function duel(): BelongsTo
    {
        return $this->belongsTo(Duel::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function challengerAnswer(): BelongsTo
    {
        return $this->belongsTo(Answer::class, 'challenger_answer_id');
    }

    public function opponentAnswer(): BelongsTo
    {
        return $this->belongsTo(Answer::class, 'opponent_answer_id');
    }

    public function hasPlayerAnswered(User $user): bool
    {
        $duel = $this->duel;
        if ($duel->isChallenger($user)) {
            return $this->challenger_answer_id !== null;
        }
        return $this->opponent_answer_id !== null;
    }

    public function bothAnswered(): bool
    {
        return $this->challenger_answer_id !== null && $this->opponent_answer_id !== null;
    }

    public function isPending(): bool
    {
        return $this->round_result === 'pending';
    }

    public function recordAnswer(User $user, Answer $answer): void
    {
        $duel = $this->duel;
        $isCorrect = $answer->is_correct;

        if ($duel->isChallenger($user)) {
            $this->challenger_answer_id = $answer->id;
            $this->challenger_is_correct = $isCorrect;
            $this->challenger_answered_at = now();
        } else {
            $this->opponent_answer_id = $answer->id;
            $this->opponent_is_correct = $isCorrect;
            $this->opponent_answered_at = now();
        }

        $this->save();
    }

    public function calculateRoundResult(): string
    {
        // If both haven't answered yet, return pending
        if (!$this->bothAnswered()) {
            return 'pending';
        }

        $challengerCorrect = $this->challenger_is_correct ?? false;
        $opponentCorrect = $this->opponent_is_correct ?? false;

        if ($challengerCorrect && !$opponentCorrect) {
            return 'challenger_wins';
        }

        if (!$challengerCorrect && $opponentCorrect) {
            return 'opponent_wins';
        }

        // Both correct or both incorrect
        return 'draw';
    }

    public function finalizeRound(): bool
    {
        // Use atomic update to prevent race condition
        $updated = static::where('id', $this->id)
            ->where('round_result', 'pending')
            ->update([
                'round_result' => $this->calculateRoundResult(),
                'ended_at' => now(),
            ]);

        if ($updated === 0) {
            return false; // Already processed by another request
        }

        // Refresh model to get updated values
        $this->refresh();
        return true;
    }
}
