<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private channel for user notifications (friend requests, duel invites)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Presence channel for duels
Broadcast::channel('duel.{roomCode}', function ($user, $roomCode) {
    $duel = \App\Models\Duel::where('room_code', $roomCode)
        ->where(function ($query) use ($user) {
            $query->where('challenger_id', $user->id)
                  ->orWhere('opponent_id', $user->id);
        })
        ->first();

    if (!$duel) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name ?? $user->first_name,
        'username' => $user->username,
        'photo_url' => $user->photo_url,
    ];
});
