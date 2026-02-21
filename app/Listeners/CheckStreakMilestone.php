<?php

namespace App\Listeners;

use App\Events\ActivityCompleted;
use App\Services\StreakService;
use App\Services\TelegramNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CheckStreakMilestone implements ShouldQueue
{
    public function __construct(
        protected StreakService $streakService,
        protected TelegramNotificationService $notificationService
    ) {}

    public function handle(ActivityCompleted $event): void
    {
        $user = $event->user;

        // Check if user has notifications enabled
        if (!$user->hasStreakNotificationsEnabled()) {
            return;
        }

        // Check if user has telegram_id
        if (!$user->telegram_id) {
            return;
        }

        // Calculate current streak
        $streak = $this->streakService->calculateStreak($user->id);

        // Check if it's a milestone
        if (!$this->streakService->isMilestone($streak)) {
            return;
        }

        // Check if we already sent milestone notification today
        if ($this->notificationService->wasNotificationSentToday($user, 'milestone')) {
            return;
        }

        // Send milestone notification
        $this->notificationService->sendMilestoneNotification($user, $streak);
    }
}
