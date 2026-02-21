<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'scenario',
        'streak_count',
        'sent_successfully',
        'error_message',
        'notification_date',
    ];

    protected function casts(): array
    {
        return [
            'streak_count' => 'integer',
            'sent_successfully' => 'boolean',
            'notification_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForToday($query)
    {
        return $query->where('notification_date', today());
    }

    public function scopeSuccessful($query)
    {
        return $query->where('sent_successfully', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOfScenario($query, string $scenario)
    {
        return $query->where('scenario', $scenario);
    }
}
