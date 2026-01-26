<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuelStats extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'opponent_id',
        'total_duels',
        'wins',
        'losses',
        'draws',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    public static function recordResult(User $user, User $opponent, string $result): void
    {
        // Update stats for user
        $userStats = self::firstOrCreate(
            ['user_id' => $user->id, 'opponent_id' => $opponent->id],
            ['total_duels' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0]
        );

        // Update stats for opponent (mirror)
        $opponentStats = self::firstOrCreate(
            ['user_id' => $opponent->id, 'opponent_id' => $user->id],
            ['total_duels' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0]
        );

        $userStats->total_duels++;
        $opponentStats->total_duels++;

        switch ($result) {
            case 'win':
                $userStats->wins++;
                $opponentStats->losses++;
                break;
            case 'loss':
                $userStats->losses++;
                $opponentStats->wins++;
                break;
            case 'draw':
                $userStats->draws++;
                $opponentStats->draws++;
                break;
        }

        $userStats->save();
        $opponentStats->save();
    }

    public static function getStatsBetween(User $user, User $opponent): ?self
    {
        return self::where('user_id', $user->id)
            ->where('opponent_id', $opponent->id)
            ->first();
    }

    public function getWinRate(): float
    {
        if ($this->total_duels === 0) {
            return 0;
        }
        return round(($this->wins / $this->total_duels) * 100, 1);
    }
}
