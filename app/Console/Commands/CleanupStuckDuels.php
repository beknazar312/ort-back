<?php

namespace App\Console\Commands;

use App\Events\DuelEnded;
use App\Models\Duel;
use App\Models\DuelStats;
use Illuminate\Console\Command;

class CleanupStuckDuels extends Command
{
    protected $signature = 'duels:cleanup';
    protected $description = 'Cleanup stuck/expired duels';

    public function handle(): int
    {
        $this->info('Cleaning up stuck duels...');

        // Find active duels that haven't been updated in 1 hour
        $stuckActiveDuels = Duel::where('status', 'active')
            ->where('updated_at', '<', now()->subHour())
            ->get();

        foreach ($stuckActiveDuels as $duel) {
            $winner = $duel->determineWinner();
            $duel->update([
                'status' => 'expired',
                'winner_id' => $winner?->id,
                'completed_at' => now(),
            ]);

            // Update stats
            if ($winner) {
                $loser = $winner->id === $duel->challenger_id ? $duel->opponent : $duel->challenger;
                DuelStats::recordResult($winner, $loser, 'win');
            } else {
                DuelStats::recordResult($duel->challenger, $duel->opponent, 'draw');
            }

            broadcast(new DuelEnded($duel));
            $this->info("Marked duel #{$duel->id} as expired");
        }

        // Find pending duels older than 1 hour
        $stuckPendingDuels = Duel::where('status', 'pending')
            ->where('created_at', '<', now()->subHour())
            ->get();

        foreach ($stuckPendingDuels as $duel) {
            $duel->update([
                'status' => 'expired',
                'completed_at' => now(),
            ]);
            $this->info("Marked pending duel #{$duel->id} as expired");
        }

        $total = $stuckActiveDuels->count() + $stuckPendingDuels->count();
        $this->info("Cleaned up {$total} stuck duels");

        return 0;
    }
}
