<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'text',
        'explanation',
        'difficulty',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class)->orderBy('sort_order');
    }

    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class, 'test_questions')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function testAttemptAnswers(): HasMany
    {
        return $this->hasMany(TestAttemptAnswer::class);
    }

    public function correctAnswer()
    {
        return $this->answers()->where('is_correct', true)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }
}
