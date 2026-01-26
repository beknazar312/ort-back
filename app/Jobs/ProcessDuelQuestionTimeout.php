<?php

namespace App\Jobs;

use App\Events\DuelEnded;
use App\Events\NewQuestion;
use App\Events\RoundResult;
use App\Models\Duel;
use App\Models\DuelQuestion;
use App\Models\DuelStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDuelQuestionTimeout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $duelId,
        public int $questionOrder
    ) {}

    public function handle(): void
    {
        $duel = Duel::find($this->duelId);

        if (!$duel || !$duel->isActive()) {
            return;
        }

        // Check if we're still on the same question
        if ($duel->current_question_index !== $this->questionOrder) {
            return;
        }

        $currentQuestion = $duel->currentQuestion();

        if (!$currentQuestion || !$currentQuestion->isPending()) {
            return;
        }

        // Track who timed out (didn't answer)
        $challengerTimedOut = $currentQuestion->challenger_answer_id === null;
        $opponentTimedOut = $currentQuestion->opponent_answer_id === null;

        // Mark unanswered players as incorrect (null answer = timeout)
        if ($challengerTimedOut) {
            $currentQuestion->challenger_is_correct = false;
            $currentQuestion->challenger_answered_at = now();
        }

        if ($opponentTimedOut) {
            $currentQuestion->opponent_is_correct = false;
            $currentQuestion->opponent_answered_at = now();
        }

        $currentQuestion->save();

        // Finalize the round atomically - returns false if already processed
        if (!$currentQuestion->finalizeRound()) {
            return; // Already processed by another request or player answers
        }

        // Apply life changes: players who timed out lose a life (as if they answered incorrectly)
        // This is different from round result - timeout always costs a life
        if ($challengerTimedOut) {
            $duel->decrementLives($duel->challenger);
        }
        if ($opponentTimedOut) {
            $duel->decrementLives($duel->opponent);
        }

        // Refresh model to get updated lives
        $duel->refresh();

        // Broadcast round result
        broadcast(new RoundResult($duel, $currentQuestion));

        // Check if game is over
        if ($duel->checkGameOver()) {
            $winner = $duel->determineWinner();

            $duel->update([
                'status' => 'completed',
                'winner_id' => $winner?->id,
                'completed_at' => now(),
            ]);

            if ($winner) {
                $loser = $winner->id === $duel->challenger_id ? $duel->opponent : $duel->challenger;
                DuelStats::recordResult($winner, $loser, 'win');
            } else {
                // Both players have 0 lives - it's a draw
                DuelStats::recordResult($duel->challenger, $duel->opponent, 'draw');
            }

            broadcast(new DuelEnded($duel));
            return;
        }

        // Move to next question
        $duel->increment('current_question_index');
        $nextQuestion = $duel->currentQuestion();

        if ($nextQuestion) {
            $nextQuestion->update(['started_at' => now()]);
            $nextQuestion->load('question.answers');

            broadcast(new NewQuestion($duel, $nextQuestion));

            // Schedule timeout for next question
            self::dispatch($duel->id, $nextQuestion->question_order)
                ->delay(now()->addSeconds($duel->time_per_question + 2));
            return;
        }

        // No more questions - end the duel
        $winner = $duel->determineWinner();

        $duel->update([
            'status' => 'completed',
            'winner_id' => $winner?->id,
            'completed_at' => now(),
        ]);

        if ($winner) {
            $loser = $winner->id === $duel->challenger_id ? $duel->opponent : $duel->challenger;
            DuelStats::recordResult($winner, $loser, 'win');
        } else {
            // Both players have same lives - it's a draw
            DuelStats::recordResult($duel->challenger, $duel->opponent, 'draw');
        }

        broadcast(new DuelEnded($duel));
    }
}
