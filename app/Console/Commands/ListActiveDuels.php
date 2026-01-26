<?php

namespace App\Console\Commands;

use App\Models\Duel;
use Illuminate\Console\Command;

class ListActiveDuels extends Command
{
    protected $signature = 'duels:list {--all : Show all duels including completed}';
    protected $description = 'List active and pending duels';

    public function handle(): int
    {
        $query = Duel::with(['challenger', 'opponent']);

        if (!$this->option('all')) {
            $query->whereIn('status', ['pending', 'active']);
        }

        $duels = $query->orderByDesc('created_at')->get();

        if ($duels->isEmpty()) {
            $this->info('No duels found');
            return 0;
        }

        $headers = ['ID', 'Status', 'Challenger', 'Opponent', 'Lives', 'Created', 'Updated'];
        $rows = [];

        foreach ($duels as $duel) {
            $rows[] = [
                $duel->id,
                $duel->status,
                $duel->challenger->name ?? 'N/A',
                $duel->opponent->name ?? 'N/A',
                "{$duel->challenger_lives}/{$duel->opponent_lives}",
                $duel->created_at->format('Y-m-d H:i:s'),
                $duel->updated_at->format('Y-m-d H:i:s'),
            ];
        }

        $this->table($headers, $rows);
        $this->info("Total: {$duels->count()} duels");

        return 0;
    }
}
