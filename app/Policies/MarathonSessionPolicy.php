<?php

namespace App\Policies;

use App\Models\MarathonSession;
use App\Models\User;

class MarathonSessionPolicy
{
    public function view(User $user, MarathonSession $session): bool
    {
        return $user->id === $session->user_id;
    }

    public function update(User $user, MarathonSession $session): bool
    {
        return $user->id === $session->user_id;
    }
}
