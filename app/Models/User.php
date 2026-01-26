<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'telegram_id',
        'name',
        'username',
        'first_name',
        'last_name',
        'photo_url',
        'language_code',
        'is_premium',
        'is_admin',
        'telegram_auth_date',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'telegram_id' => 'integer',
            'is_premium' => 'boolean',
            'is_admin' => 'boolean',
            'telegram_auth_date' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function testAttempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    // Friendship relationships - outgoing friend requests
    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'user_id');
    }

    // Friendship relationships - incoming friend requests
    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'friend_id');
    }

    // Get all accepted friends (both directions)
    public function friends(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')
            ->wherePivot('status', 'accepted')
            ->withPivot('status', 'accepted_at')
            ->withTimestamps();
    }

    // Check if user is friends with another user
    public function isFriendWith(User $user): bool
    {
        return Friendship::where(function ($query) use ($user) {
            $query->where('user_id', $this->id)
                  ->where('friend_id', $user->id);
        })->orWhere(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->where('friend_id', $this->id);
        })->where('status', 'accepted')->exists();
    }

    // Check if there's a pending request between users
    public function hasPendingFriendRequestWith(User $user): bool
    {
        return Friendship::where(function ($query) use ($user) {
            $query->where('user_id', $this->id)
                  ->where('friend_id', $user->id);
        })->orWhere(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->where('friend_id', $this->id);
        })->where('status', 'pending')->exists();
    }

    // Get pending incoming friend requests
    public function pendingFriendRequests(): HasMany
    {
        return $this->receivedFriendRequests()->where('status', 'pending');
    }

    // Duel relationships
    public function challengedDuels(): HasMany
    {
        return $this->hasMany(Duel::class, 'challenger_id');
    }

    public function opponentDuels(): HasMany
    {
        return $this->hasMany(Duel::class, 'opponent_id');
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public static function findByTelegramId(int $telegramId): ?self
    {
        return self::where('telegram_id', $telegramId)->first();
    }

    public static function createFromTelegram(array $telegramUser): self
    {
        return self::create([
            'telegram_id' => $telegramUser['id'],
            'first_name' => $telegramUser['first_name'] ?? null,
            'last_name' => $telegramUser['last_name'] ?? null,
            'username' => $telegramUser['username'] ?? null,
            'photo_url' => $telegramUser['photo_url'] ?? null,
            'language_code' => $telegramUser['language_code'] ?? null,
            'is_premium' => $telegramUser['is_premium'] ?? false,
            'name' => trim(($telegramUser['first_name'] ?? '') . ' ' . ($telegramUser['last_name'] ?? '')),
            'telegram_auth_date' => isset($telegramUser['auth_date'])
                ? \Carbon\Carbon::createFromTimestamp($telegramUser['auth_date'])
                : now(),
        ]);
    }

    public function updateFromTelegram(array $telegramUser): self
    {
        $this->update([
            'first_name' => $telegramUser['first_name'] ?? $this->first_name,
            'last_name' => $telegramUser['last_name'] ?? $this->last_name,
            'username' => $telegramUser['username'] ?? $this->username,
            'photo_url' => $telegramUser['photo_url'] ?? $this->photo_url,
            'language_code' => $telegramUser['language_code'] ?? $this->language_code,
            'is_premium' => $telegramUser['is_premium'] ?? $this->is_premium,
            'name' => trim(($telegramUser['first_name'] ?? '') . ' ' . ($telegramUser['last_name'] ?? '')),
            'telegram_auth_date' => isset($telegramUser['auth_date'])
                ? \Carbon\Carbon::createFromTimestamp($telegramUser['auth_date'])
                : now(),
        ]);

        return $this;
    }
}
