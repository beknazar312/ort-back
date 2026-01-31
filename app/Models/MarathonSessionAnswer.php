<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarathonSessionAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'marathon_session_id',
        'question_id',
        'answer_id',
        'is_correct',
        'time_remaining',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'time_remaining' => 'decimal:2',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(MarathonSession::class, 'marathon_session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }
}
