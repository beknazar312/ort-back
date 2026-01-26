<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Duel extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_code',
        'challenger_id',
        'opponent_id',
        'subject_id',
        'winner_id',
        'initial_lives',
        'time_per_question',
        'challenger_lives',
        'opponent_lives',
        'current_question_index',
        'status',
        'surrendered_by',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($duel) {
            if (!$duel->room_code) {
                $duel->room_code = Str::uuid()->toString();
            }
        });
    }

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function surrenderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surrendered_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(DuelQuestion::class)->orderBy('question_order');
    }

    public function currentQuestion(): ?DuelQuestion
    {
        return $this->questions()->where('question_order', $this->current_question_index)->first();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'surrendered', 'declined', 'expired']);
    }

    public function isParticipant(User $user): bool
    {
        return $this->challenger_id === $user->id || $this->opponent_id === $user->id;
    }

    public function isChallenger(User $user): bool
    {
        return $this->challenger_id === $user->id;
    }

    public function isOpponent(User $user): bool
    {
        return $this->opponent_id === $user->id;
    }

    public function getPlayerLives(User $user): int
    {
        return $this->isChallenger($user) ? $this->challenger_lives : $this->opponent_lives;
    }

    public function getOpponentLives(User $user): int
    {
        return $this->isChallenger($user) ? $this->opponent_lives : $this->challenger_lives;
    }

    public function decrementLives(User $user): void
    {
        if ($this->isChallenger($user)) {
            $this->challenger_lives = max(0, $this->challenger_lives - 1);
        } else {
            $this->opponent_lives = max(0, $this->opponent_lives - 1);
        }
        $this->save();
    }

    public function checkGameOver(): bool
    {
        return $this->challenger_lives <= 0 || $this->opponent_lives <= 0;
    }

    public function determineWinner(): ?User
    {
        // If both have 0 lives, it's a draw (no winner)
        if ($this->challenger_lives <= 0 && $this->opponent_lives <= 0) {
            return null;
        }
        if ($this->challenger_lives <= 0) {
            return $this->opponent;
        }
        if ($this->opponent_lives <= 0) {
            return $this->challenger;
        }
        // If both have lives remaining, compare them
        if ($this->challenger_lives > $this->opponent_lives) {
            return $this->challenger;
        }
        if ($this->opponent_lives > $this->challenger_lives) {
            return $this->opponent;
        }
        return null;
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'surrendered', 'declined', 'expired']);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('challenger_id', $user->id)
              ->orWhere('opponent_id', $user->id);
        });
    }
}
