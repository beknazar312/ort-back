<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'test_id',
        'subject_id',
        'mode',
        'total_questions',
        'correct_answers',
        'score',
        'time_spent_seconds',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_questions' => 'integer',
            'correct_answers' => 'integer',
            'score' => 'decimal:2',
            'time_spent_seconds' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TestAttemptAnswer::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isPractice(): bool
    {
        return $this->mode === 'practice';
    }

    public function isTest(): bool
    {
        return $this->mode === 'test';
    }

    public function calculateScore(): void
    {
        $this->score = $this->total_questions > 0
            ? round(($this->correct_answers / $this->total_questions) * 100, 2)
            : 0;
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopePractice($query)
    {
        return $query->where('mode', 'practice');
    }

    public function scopeTest($query)
    {
        return $query->where('mode', 'test');
    }
}
