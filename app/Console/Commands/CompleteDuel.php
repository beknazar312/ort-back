<?php

namespace App\Console\Commands;

use App\Events\DuelEnded;
use App\Models\Duel;
use App\Models\DuelStats;
use Illuminate\Console\Command;

class CompleteDuel extends Command
{
    protected $signature = 'duel:complete {id : The duel ID to complete}';
    protected $description = 'Manually complete a stuck duel';

    public function handle(): int
    {
        $duelId = $this->argument('id');
        $duel = Duel::find($duelId);

        if (!$duel) {
            $this->error("Duel #{$duelId} not found");
            return 1;
        }

        if ($duel->isCompleted()) {
            $this->info("Duel #{$duelId} is already completed with status: {$duel->status}");
            return 0;
        }

        $this->info("Current status: {$duel->status}");
        $this->info("Challenger lives: {$duel->challenger_lives}");
        $this->info("Opponent lives: {$duel->opponent_lives}");

        if (!$this->confirm('Do you want to complete this duel?')) {
            return 0;
        }

        $winner = $duel->determineWinner();

        $duel->update([
            'status' => 'completed',
            'winner_id' => $winner?->id,
            'completed_at' => now(),
        ]);

        // Update stats
        if ($winner) {
            $loser = $winner->id === $duel->challenger_id ? $duel->opponent : $duel->challenger;
            DuelStats::recordResult($winner, $loser, 'win');
            $this->info("Winner: {$winner->name}");
        } else {
            DuelStats::recordResult($duel->challenger, $duel->opponent, 'draw');
            $this->info("Result: Draw");
        }

        broadcast(new DuelEnded($duel));
        $this->info("Duel #{$duelId} completed successfully");

        return 0;
    }
}
